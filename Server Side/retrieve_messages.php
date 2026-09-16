<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once dirname(dirname(__DIR__)) . '/site_config.php';  // ~/site_config.php

$conn = site_db(site_secrets());

$limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Clamp so a crafted ?limit=999999 can't ask the server for the whole table.
$limit  = max(1, min($limit, 100));
$offset = max(0, $offset);

$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

// ?id=N fetches one post in full (the essay page). Otherwise, a page of the feed.
$single_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Essay body in the feed is only used for a short preview, so don't ship the
// whole thing for every essay on the page.
const FEED_ESSAY_CHARS = 2000;

if ($single_id !== null) {
    $stmt = $conn->prepare(
        "SELECT id, sender_name, subject, message, image_url, received_at
         FROM messages WHERE id = ? AND parent_id IS NULL AND deleted_at IS NULL"
    );
    $stmt->bind_param("i", $single_id);
} else {
    $stmt = $conn->prepare(
        "SELECT id, sender_name, subject, message, image_url, received_at
         FROM messages WHERE parent_id IS NULL AND deleted_at IS NULL
         ORDER BY received_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
$ids = [];
while ($row = $result->fetch_assoc()) {
    // A subject of "Essay: Some Title" marks an essay. The subject is stored as
    // sent, so this also applies to anything already in the database.
    if (preg_match('/^\s*Essay:\s*(.*)$/is', $row['subject'] ?? '', $em)) {
        $row['post_type'] = 'essay';
        $row['title']     = trim($em[1]);
        if ($single_id === null && mb_strlen($row['message']) > FEED_ESSAY_CHARS) {
            $row['message'] = mb_substr($row['message'], 0, FEED_ESSAY_CHARS);
        }
    } else {
        $row['post_type'] = 'post';
        $row['title']     = null;
    }
    $row['poll'] = null;
    $row['replies'] = [];
    $messages[$row['id']] = $row;
    $ids[] = (int)$row['id'];
}
$stmt->close();

// Polls for this page of messages, in three queries total rather than three per
// message — at 100 posts the old per-message version issued 300 round trips.
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $ps = $conn->prepare("SELECT id, message_id, options FROM polls WHERE message_id IN ($in)");
    $ps->bind_param($types, ...$ids);
    $ps->execute();
    $pr = $ps->get_result();

    $polls = [];
    while ($p = $pr->fetch_assoc()) {
        $options = json_decode($p['options'], true) ?: [];
        $polls[(int)$p['id']] = [
            'message_id' => (int)$p['message_id'],
            'options'    => $options,
            'votes'      => array_fill(0, count($options), 0),
            'user_voted' => null,
        ];
    }
    $ps->close();

    if ($polls) {
        $pids  = array_keys($polls);
        $pin   = implode(',', array_fill(0, count($pids), '?'));
        $ptype = str_repeat('i', count($pids));

        // Vote tallies
        $vs = $conn->prepare(
            "SELECT poll_id, option_index, COUNT(*) AS cnt
             FROM poll_votes WHERE poll_id IN ($pin) GROUP BY poll_id, option_index"
        );
        $vs->bind_param($ptype, ...$pids);
        $vs->execute();
        $vr = $vs->get_result();
        while ($v = $vr->fetch_assoc()) {
            $pid = (int)$v['poll_id'];
            $idx = (int)$v['option_index'];
            if (isset($polls[$pid]['votes'][$idx])) {
                $polls[$pid]['votes'][$idx] = (int)$v['cnt'];
            }
        }
        $vs->close();

        // Which of them this visitor has already voted in
        $os = $conn->prepare(
            "SELECT poll_id, option_index FROM poll_votes
             WHERE poll_id IN ($pin) AND ip_hash = ?"
        );
        $os->bind_param($ptype . 's', ...array_merge($pids, [$ip_hash]));
        $os->execute();
        $or = $os->get_result();
        while ($o = $or->fetch_assoc()) {
            $polls[(int)$o['poll_id']]['user_voted'] = (int)$o['option_index'];
        }
        $os->close();

        foreach ($polls as $pid => $p) {
            $mid = $p['message_id'];
            if (!isset($messages[$mid])) continue;
            unset($p['message_id']);
            $messages[$mid]['poll'] = ['id' => $pid] + $p;
        }
    }
}

// Replies for every post on this page, in one query, oldest first.
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rs = $conn->prepare(
        "SELECT id, parent_id, message, image_url, received_at
         FROM messages WHERE parent_id IN ($in) AND deleted_at IS NULL ORDER BY received_at ASC, id ASC"
    );
    $rs->bind_param(str_repeat('i', count($ids)), ...$ids);
    $rs->execute();
    $rr = $rs->get_result();
    while ($reply = $rr->fetch_assoc()) {
        $pid = (int)$reply['parent_id'];
        if (!isset($messages[$pid])) continue;
        $messages[$pid]['replies'][] = [
            'id'          => (int)$reply['id'],
            'message'     => $reply['message'],
            'image_url'   => $reply['image_url'],
            'received_at' => $reply['received_at'],
        ];
    }
    $rs->close();
}

// Top-level posts only — replies live inside their post, not in the count.
$total = (int)$conn->query("SELECT COUNT(*) AS total FROM messages WHERE parent_id IS NULL AND deleted_at IS NULL")->fetch_assoc()['total'];
$conn->close();

if ($single_id !== null) {
    if (!$messages) site_fail(404, "Post not found");
    echo json_encode(["messages" => array_values($messages)]);
    exit;
}

echo json_encode([
    "messages" => array_values($messages),
    "hasMore"  => ($offset + $limit) < $total,
    "total"    => $total,
]);

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

// Messages
$stmt = $conn->prepare(
    "SELECT id, sender_name, subject, message, image_url, received_at
     FROM messages ORDER BY received_at DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
$ids = [];
while ($row = $result->fetch_assoc()) {
    $row['poll'] = null;
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

$total = (int)$conn->query("SELECT COUNT(*) AS total FROM messages")->fetch_assoc()['total'];
$conn->close();

echo json_encode([
    "messages" => array_values($messages),
    "hasMore"  => ($offset + $limit) < $total,
    "total"    => $total,
]);

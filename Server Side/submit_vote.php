<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once dirname(dirname(__DIR__)) . '/site_config.php';  // ~/site_config.php

$body       = json_decode(file_get_contents('php://input'), true);
$poll_id    = isset($body['poll_id'])      ? (int)$body['poll_id']      : null;
$option_idx = isset($body['option_index']) ? (int)$body['option_index'] : null;

if ($poll_id === null || $option_idx === null) {
    site_fail(400, "Missing poll_id or option_index");
}

$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
$conn = site_db(site_secrets());

// Poll must exist on THIS site's database, and the option must be real.
$ps = $conn->prepare("SELECT id, options FROM polls WHERE id = ?");
$ps->bind_param("i", $poll_id);
$ps->execute();
$poll = $ps->get_result()->fetch_assoc();
$ps->close();

if (!$poll) site_fail(404, "Poll not found");

$options = json_decode($poll['options'], true) ?: [];
if ($option_idx < 0 || $option_idx >= count($options)) {
    site_fail(400, "Invalid option");
}

// INSERT IGNORE + the UNIQUE KEY on (poll_id, ip_hash) makes a second vote a no-op.
$vs = $conn->prepare("INSERT IGNORE INTO poll_votes (poll_id, option_index, ip_hash) VALUES (?, ?, ?)");
$vs->bind_param("iis", $poll_id, $option_idx, $ip_hash);
$vs->execute();
$new_vote = $vs->affected_rows > 0;
$vs->close();

// Whatever the outcome, report the vote actually on record for this visitor.
$es = $conn->prepare("SELECT option_index FROM poll_votes WHERE poll_id = ? AND ip_hash = ?");
$es->bind_param("is", $poll_id, $ip_hash);
$es->execute();
$existing = $es->get_result()->fetch_assoc();
$es->close();

$cr = $conn->prepare("SELECT option_index, COUNT(*) AS cnt FROM poll_votes WHERE poll_id = ? GROUP BY option_index");
$cr->bind_param("i", $poll_id);
$cr->execute();
$rows  = $cr->get_result();
$votes = array_fill(0, count($options), 0);
while ($r = $rows->fetch_assoc()) {
    $votes[(int)$r['option_index']] = (int)$r['cnt'];
}
$cr->close();
$conn->close();

echo json_encode([
    "success"    => true,
    "new_vote"   => $new_vote,
    "votes"      => $votes,
    "user_voted" => $existing ? (int)$existing['option_index'] : $option_idx,
]);

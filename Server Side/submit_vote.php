<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$body       = json_decode(file_get_contents('php://input'), true);
$poll_id    = isset($body['poll_id'])     ? (int)$body['poll_id']     : null;
$option_idx = isset($body['option_index']) ? (int)$body['option_index'] : null;

if ($poll_id === null || $option_idx === null) {
    http_response_code(400);
    echo json_encode(["error" => "Missing poll_id or option_index"]);
    exit;
}

$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
$secrets = include('/home/vuc923ya50qu/secrets.php');

$conn = new mysqli($secrets['db']['host'], $secrets['db']['user'], $secrets['db']['pass'], $secrets['db']['dbname']);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB error"]);
    exit;
}

// Verify poll exists and option index is valid
$ps = $conn->prepare("SELECT id, options FROM polls WHERE id = ?");
$ps->bind_param("i", $poll_id);
$ps->execute();
$poll = $ps->get_result()->fetch_assoc();
$ps->close();

if (!$poll) {
    http_response_code(404);
    echo json_encode(["error" => "Poll not found"]);
    exit;
}

$options = json_decode($poll['options'], true);
if ($option_idx < 0 || $option_idx >= count($options)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid option"]);
    exit;
}

// Insert vote — IGNORE silently handles duplicate (already voted)
$vs = $conn->prepare("INSERT IGNORE INTO poll_votes (poll_id, option_index, ip_hash) VALUES (?, ?, ?)");
$vs->bind_param("iis", $poll_id, $option_idx, $ip_hash);
$vs->execute();
$new_vote = $vs->affected_rows > 0;
$vs->close();

// Return updated counts
$cr = $conn->prepare("SELECT option_index, COUNT(*) as cnt FROM poll_votes WHERE poll_id = ? GROUP BY option_index");
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
    "user_voted" => $option_idx,
]);

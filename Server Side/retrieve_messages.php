<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$secrets = include('/home/vuc923ya50qu/secrets.php');

$conn = new mysqli(
    $secrets['db']['host'],
    $secrets['db']['user'],
    $secrets['db']['pass'],
    $secrets['db']['dbname']
);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

// Fetch messages
$sql = "SELECT id, sender_name, subject, message, image_url, received_at FROM messages ORDER BY received_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

// Attach poll data to each message
foreach ($messages as &$msg) {
    $ps = $conn->prepare("SELECT id, options FROM polls WHERE message_id = ?");
    $ps->bind_param("i", $msg['id']);
    $ps->execute();
    $poll = $ps->get_result()->fetch_assoc();
    $ps->close();

    if ($poll) {
        $poll_id = (int)$poll['id'];
        $options = json_decode($poll['options'], true);

        // Vote counts per option
        $vs = $conn->prepare("SELECT option_index, COUNT(*) as cnt FROM poll_votes WHERE poll_id = ? GROUP BY option_index");
        $vs->bind_param("i", $poll_id);
        $vs->execute();
        $vr = $vs->get_result();
        $votes = array_fill(0, count($options), 0);
        while ($v = $vr->fetch_assoc()) {
            $votes[(int)$v['option_index']] = (int)$v['cnt'];
        }
        $vs->close();

        // Did this IP vote?
        $ivs = $conn->prepare("SELECT option_index FROM poll_votes WHERE poll_id = ? AND ip_hash = ?");
        $ivs->bind_param("is", $poll_id, $ip_hash);
        $ivs->execute();
        $iv = $ivs->get_result()->fetch_assoc();
        $ivs->close();

        $msg['poll'] = [
            'id'         => $poll_id,
            'options'    => $options,
            'votes'      => $votes,
            'user_voted' => $iv ? (int)$iv['option_index'] : null,
        ];
    } else {
        $msg['poll'] = null;
    }
}

// Total count
$total = $conn->query("SELECT COUNT(*) as total FROM messages")->fetch_assoc()['total'];
$conn->close();

echo json_encode([
    "messages" => $messages,
    "hasMore"  => ($offset + $limit) < $total,
    "total"    => $total,
]);

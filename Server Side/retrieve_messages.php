<?php
// ---------------------------------------------------------
// CORS HEADERS — required for your React frontend
// ---------------------------------------------------------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// ---------------------------------------------------------
// LOAD SENSITIVE INFORMATION FROM secrets.php
// ---------------------------------------------------------
$secrets = include('/home/vuc923ya50qu/secrets.php');

// ---------------------------------------------------------
// CONNECT TO DATABASE
// ---------------------------------------------------------
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

// ---------------------------------------------------------
// PAGINATION PARAMETERS
// ---------------------------------------------------------
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// ---------------------------------------------------------
// FETCH MESSAGES WITH PAGINATION
// ---------------------------------------------------------
$sql = "SELECT id, sender_name, subject, message, received_at
        FROM messages 
        ORDER BY received_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
} else {
    http_response_code(500);
    echo json_encode(["error" => "Query failed"]);
    exit;
}

// ---------------------------------------------------------
// GET TOTAL COUNT (to know if there are more messages)
// ---------------------------------------------------------
$countSql = "SELECT COUNT(*) as total FROM messages";
$countResult = $conn->query($countSql);
$totalMessages = $countResult->fetch_assoc()['total'];

$stmt->close();
$conn->close();

// ---------------------------------------------------------
// OUTPUT JSON WITH METADATA
// ---------------------------------------------------------
echo json_encode([
    "messages" => $messages,
    "hasMore" => ($offset + $limit) < $totalMessages,
    "total" => $totalMessages
]);

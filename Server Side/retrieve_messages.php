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
// secrets.php MUST define:
// $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME

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
// FETCH MESSAGES
// ---------------------------------------------------------
$sql = "SELECT id, sender_name, subject, message, received_at
        FROM messages 
        ORDER BY received_at DESC";

$result = $conn->query($sql);

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

$conn->close();

// ---------------------------------------------------------
// OUTPUT JSON
// ---------------------------------------------------------
echo json_encode($messages);

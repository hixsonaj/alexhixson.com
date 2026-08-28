#!/usr/bin/php -q
<?php
ini_set('display_errors', 0);
error_reporting(0);

$log = '/home/vuc923ya50qu/mail/alexhixson.zerofour.tech/message/mail_debug.log';
file_put_contents($log, date('Y-m-d H:i:s') . " — script started\n", FILE_APPEND);

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $log);
error_reporting(E_ALL);

require_once '/home/vuc923ya50qu/vendor/autoload.php';
use ZBateson\MailMimeParser\MailMimeParser;

$parser = new MailMimeParser();
$mail   = $parser->parse(fopen('php://stdin', 'r'), true);

// ---- Sender ----
$fromHeader = $mail->getHeader('from');
$name       = $fromHeader ? trim($fromHeader->getPersonName(), "\"' ") : '';
$email      = $fromHeader ? strtolower(trim($fromHeader->getEmail())) : '';
if ($name === '' && $email !== '') {
    $name = substr($email, 0, strpos($email, '@'));
}

$subject = $mail->getHeaderValue('subject') ?? '';

// ---- Text content ----
$text = $mail->getTextContent();
if ($text === null) {
    $html = $mail->getHtmlContent();
    $text = $html !== null ? strip_tags($html) : '';
}
$clean_message = trim($text);
if (mb_strlen($clean_message) > 2000) {
    $clean_message = mb_substr($clean_message, 0, 2000);
}

// ---- Parse poll from subject ----
// Format: "Poll: option1, option2, option3"
$poll_options = null;
if (preg_match('/^Poll:\s*(.+)$/i', $subject, $m)) {
    $raw_options = array_map('trim', explode(',', $m[1]));
    $raw_options = array_filter($raw_options, fn($o) => $o !== '');
    if (count($raw_options) >= 2) {
        $poll_options = array_values($raw_options);
    }
    $subject = ''; // subject was just for poll config, clear it
}

file_put_contents($log, date('Y-m-d H:i:s') . " — parsed. from='$email' poll=" . ($poll_options ? implode(',', $poll_options) : 'none') . "\n", FILE_APPEND);

// ---- Load secrets ----
$secrets        = include('/home/vuc923ya50qu/secrets.php');
$allowedSenders = array_map('strtolower', $secrets['allowed_senders']);
$subjectToken   = $secrets['subject_token'] ?? '';
$tokenInSubject = ($subjectToken !== '') && (strpos($subject, $subjectToken) !== false);
$emailAllowed   = ($email !== '' && in_array($email, $allowedSenders, true));

if (!$emailAllowed && !$tokenInSubject) {
    $inv = '/home/vuc923ya50qu/mail/alexhixson.zerofour.tech/message/invalid_access.log';
    file_put_contents($inv, date('Y-m-d H:i:s') . " - Rejected: sender='$email'\n", FILE_APPEND);
    exit(0);
}

if ($tokenInSubject) {
    $subject = trim(preg_replace('/\s+/', ' ', str_replace($subjectToken, '', $subject)));
}

// ---- Connect to DB ----
$mysqli = new mysqli($secrets['db']['host'], $secrets['db']['user'], $secrets['db']['pass'], $secrets['db']['dbname']);
if ($mysqli->connect_error) {
    file_put_contents($log, date('Y-m-d H:i:s') . " — DB error: " . $mysqli->connect_error . "\n", FILE_APPEND);
    exit(0);
}

// ---- Image attachment ----
$image_url = null;
$allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$img_dir = '/home/vuc923ya50qu/public_html/alexhixson.zerofour.tech/post-images/';
$img_base_url = 'https://alexhixson.zerofour.tech/post-images/';

$ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
foreach ($mail->getAllAttachmentParts() as $part) {
    $mime = strtolower($part->getHeaderValue('Content-Type') ?? '');
    $mime = explode(';', $mime)[0];
    if (!isset($ext_map[$mime])) continue;

    $filename = 'img_' . uniqid('', true) . '.' . $ext_map[$mime];
    $filepath = $img_dir . $filename;

    $content = $part->getBinaryContentResourceHandle();
    if ($content && file_put_contents($filepath, stream_get_contents($content)) !== false) {
        chmod($filepath, 0644);
        $image_url = $img_base_url . $filename;
        file_put_contents($log, date('Y-m-d H:i:s') . " — saved image: $filename\n", FILE_APPEND);
    }
    break; // only save first image
}

// ---- Insert message ----
$stmt = $mysqli->prepare("INSERT INTO messages (sender_name, sender_email, subject, message, image_url, received_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("sssss", $name, $email, $subject, $clean_message, $image_url);
$stmt->execute();
$message_id = $mysqli->insert_id;
$stmt->close();

// ---- Insert poll if present ----
if ($poll_options && $message_id) {
    $options_json = json_encode($poll_options);
    $ps = $mysqli->prepare("INSERT INTO polls (message_id, options) VALUES (?, ?)");
    $ps->bind_param("is", $message_id, $options_json);
    $ps->execute();
    $ps->close();
    file_put_contents($log, date('Y-m-d H:i:s') . " — poll created for message $message_id\n", FILE_APPEND);
}

$mysqli->close();
file_put_contents($log, date('Y-m-d H:i:s') . " — done\n", FILE_APPEND);
exit(0);
?>

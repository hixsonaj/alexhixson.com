#!/usr/bin/php -q
<?php
ini_set('display_errors', 0);
error_reporting(0);

// ---- Logging ----
$log = '/home/vuc923ya50qu/mail/alexhixson.zerofour.tech/message/mail_debug.log';
file_put_contents($log, date('Y-m-d H:i:s') . " — script started\n", FILE_APPEND);

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $log);
error_reporting(E_ALL);

// ---- Parse email with MailMimeParser ----
require_once '/home/vuc923ya50qu/vendor/autoload.php';

use ZBateson\MailMimeParser\MailMimeParser;

$parser = new MailMimeParser();
$mail   = $parser->parse(fopen('php://stdin', 'r'), true);

// Sender
$fromHeader = $mail->getHeader('from');
$name       = $fromHeader ? trim($fromHeader->getPersonName(), "\"' ") : '';
$email      = $fromHeader ? strtolower(trim($fromHeader->getEmail())) : '';

// If no name, infer from email local part
if ($name === '' && $email !== '') {
    $name = substr($email, 0, strpos($email, '@'));
}

$subject = $mail->getHeaderValue('subject') ?? '';

// Prefer plain text; fall back to HTML with tags stripped
$text = $mail->getTextContent();
if ($text === null) {
    $html = $mail->getHtmlContent();
    $text = $html !== null ? strip_tags($html) : '';
}
$clean_message = trim($text);
if (mb_strlen($clean_message) > 2000) {
    $clean_message = mb_substr($clean_message, 0, 2000);
}

// ---- Load secrets ----
$secrets = include('/home/vuc923ya50qu/secrets.php');

// ---- Decide whether to accept message ----
$allowedSenders = array_map('strtolower', $secrets['allowed_senders']);
$subjectToken   = $secrets['subject_token'] ?? '';
$tokenInSubject = ($subjectToken !== '') && (strpos($subject, $subjectToken) !== false);
$emailAllowed   = ($email !== '' && in_array($email, $allowedSenders, true));

if (! $emailAllowed && ! $tokenInSubject) {
    $invalid_access = '/home/vuc923ya50qu/mail/alexhixson.zerofour.tech/message/invalid_access.log';
    file_put_contents($invalid_access, date('Y-m-d H:i:s') . " - Rejected: sender='$email', subject='$subject'\n", FILE_APPEND);
    exit(0);
}

// ---- Strip subject token before saving ----
if ($tokenInSubject) {
    $subject = str_replace($subjectToken, '', $subject);
    $subject = trim(preg_replace('/\s+/', ' ', $subject));
}

// ---- Connect to database ----
$mysqli = new mysqli(
    $secrets['db']['host'],
    $secrets['db']['user'],
    $secrets['db']['pass'],
    $secrets['db']['dbname']
);

if ($mysqli->connect_error) {
    exit(0); // silently fail so email doesn't bounce
}

// ---- Insert ----
$stmt = $mysqli->prepare("INSERT INTO messages (sender_name, sender_email, subject, message, received_at)
                          VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("ssss", $name, $email, $subject, $clean_message);
$stmt->execute();

$stmt->close();
$mysqli->close();

exit(0);
?>

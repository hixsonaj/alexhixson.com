#!/usr/bin/php -q
<?php
// Mail pipe. cPanel forwards a message here on stdin; this parses it and files
// it as a post.
//
// The same file serves every site. It works out which one from its own path:
//   ~/mail/<domain>/message/message.php  ->  <domain>
// so the copy under leahhixson.zerofour.tech configures itself. Pass a domain as
// the first argument to override.
//
// After any upload:  chmod 755 message.php
// SCP and FTP both drop the execute bit, and without it the pipe silently
// never runs — no error, no log line, mail just vanishes.

ini_set('display_errors', 0);
error_reporting(0);

$SITE_DOMAIN   = $argv[1] ?? basename(dirname(__DIR__));   // ~/mail/<domain>/message -> <domain>
$ACCOUNT_ROOT  = dirname(dirname(dirname(__DIR__)));   // ~/mail/<domain>/message -> ~
$log           = __DIR__ . '/mail_debug.log';

function logline($msg) {
    global $log;
    file_put_contents($log, date('Y-m-d H:i:s') . " — $msg\n", FILE_APPEND);
}

logline("script started (site=$SITE_DOMAIN)");

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $log);
error_reporting(E_ALL);

require_once $ACCOUNT_ROOT . '/vendor/autoload.php';
require_once $ACCOUNT_ROOT . '/site_config.php';
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

// ---- Poll, from the subject line: "Poll: option one, option two" ----
$poll_options = null;
if (preg_match('/^Poll:\s*(.+)$/i', $subject, $m)) {
    $raw = array_values(array_filter(array_map('trim', explode(',', $m[1])), fn($o) => $o !== ''));
    if (count($raw) >= 2) $poll_options = $raw;
    $subject = '';   // the subject was poll config, not a title
}

logline("parsed. from='$email' poll=" . ($poll_options ? implode(' | ', $poll_options) : 'none'));

// ---- This site's config ----
$cfg = site_secrets($SITE_DOMAIN, $ACCOUNT_ROOT . '/secrets.php');
if (!$cfg) {
    logline("NO CONFIG for '$SITE_DOMAIN' — check the 'sites' key in secrets.php");
    exit(0);
}

$allowedSenders = array_map('strtolower', $cfg['allowed_senders'] ?? []);
$subjectToken   = $cfg['subject_token'] ?? '';
$tokenInSubject = ($subjectToken !== '') && (stripos($subject, $subjectToken) !== false);
$emailAllowed   = ($email !== '' && in_array($email, $allowedSenders, true));

if (!$emailAllowed && !$tokenInSubject) {
    file_put_contents(__DIR__ . '/invalid_access.log',
        date('Y-m-d H:i:s') . " - Rejected: sender='$email' site='$SITE_DOMAIN'\n", FILE_APPEND);
    logline("rejected sender '$email'");
    exit(0);
}

if ($tokenInSubject) {
    $subject = trim(preg_replace('/\s+/', ' ', str_ireplace($subjectToken, '', $subject)));
}

// ---- Database ----
$db = $cfg['db'];
$mysqli = new mysqli($db['host'], $db['user'], $db['pass'], $db['dbname']);
if ($mysqli->connect_error) {
    logline("DB error: " . $mysqli->connect_error);
    exit(0);
}
$mysqli->set_charset('utf8mb4');

// ---- Image attachment (first image only) ----
$image_url = null;
$ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$img_dir      = "$ACCOUNT_ROOT/public_html/$SITE_DOMAIN/post-images/";
$img_base_url = "https://$SITE_DOMAIN/post-images/";

if (!is_dir($img_dir)) @mkdir($img_dir, 0755, true);

foreach ($mail->getAllAttachmentParts() as $part) {
    $mime = strtolower(explode(';', $part->getHeaderValue('Content-Type') ?? '')[0]);
    if (!isset($ext_map[$mime])) continue;

    $filename = 'img_' . uniqid('', true) . '.' . $ext_map[$mime];
    $handle   = $part->getBinaryContentResourceHandle();

    if ($handle && file_put_contents($img_dir . $filename, stream_get_contents($handle)) !== false) {
        chmod($img_dir . $filename, 0644);   // uploads default to unreadable by the webserver
        $image_url = $img_base_url . $filename;
        logline("saved image: $filename");
    }
    break;
}

// ---- Insert ----
$stmt = $mysqli->prepare(
    "INSERT INTO messages (sender_name, sender_email, subject, message, image_url, received_at)
     VALUES (?, ?, ?, ?, ?, NOW())"
);
$stmt->bind_param("sssss", $name, $email, $subject, $clean_message, $image_url);
$stmt->execute();
$message_id = $mysqli->insert_id;
$stmt->close();

if ($poll_options && $message_id) {
    $options_json = json_encode($poll_options, JSON_UNESCAPED_UNICODE);
    $ps = $mysqli->prepare("INSERT INTO polls (message_id, options) VALUES (?, ?)");
    $ps->bind_param("is", $message_id, $options_json);
    $ps->execute();
    $ps->close();
    logline("poll created for message $message_id");
}

$mysqli->close();
logline("done (message $message_id)");
exit(0);

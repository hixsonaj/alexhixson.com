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
require_once $ACCOUNT_ROOT . '/mail_reply.php';
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

// ---- Threading headers ----
$own_ids       = reply_ids_from_header($mail->getHeader('Message-ID')?->getRawValue());
$own_msg_id    = $own_ids[0] ?? null;
$reply_to_ids  = array_merge(
    reply_ids_from_header($mail->getHeader('In-Reply-To')?->getRawValue()),
    array_reverse(reply_ids_from_header($mail->getHeader('References')?->getRawValue()))
);
[$thread_key, $thread_is_reply] = reply_thread_index($mail->getHeader('Thread-Index')?->getRawValue());
$looks_like_reply = reply_subject_is_reply($subject) || $reply_to_ids || $thread_is_reply;
// "delete" as the subject removes the post being replied to, rather than posting.
$is_delete = reply_subject_is_delete($subject);
if ($is_delete) $looks_like_reply = true;

// ---- Text content ----
$text = $mail->getTextContent();
if ($text === null) {
    $html = $mail->getHtmlContent();
    $text = $html !== null ? strip_tags($html) : '';
}
$clean_message = trim($text);

// A reply carries the conversation below it. Keep only the new text, and hold
// on to the quoted original to identify the post if the headers can't.
$quoted_original = '';
if ($looks_like_reply) {
    [$clean_message, $quoted_original] = reply_split_quote($clean_message);
}

// Posts are short. An "Essay: Title" subject lifts the cap to what the TEXT
// column can hold (65,535 bytes), measured in bytes so a multibyte character is
// never split.
$is_essay = (bool)preg_match('/^\s*Essay:/i', $subject);
if ($is_essay) {
    if (strlen($clean_message) > 64000) {
        $clean_message = mb_strcut($clean_message, 0, 64000);
    }
} elseif (mb_strlen($clean_message) > 2000) {
    $clean_message = mb_substr($clean_message, 0, 2000);
}

// ---- Poll, from the subject line: "Poll: option one, option two" ----
$poll_options = null;
if (preg_match('/^Poll:\s*(.+)$/i', $subject, $m)) {
    $raw = array_values(array_filter(array_map('trim', explode(',', $m[1])), fn($o) => $o !== ''));
    if (count($raw) >= 2) $poll_options = $raw;
    $subject = '';   // the subject was poll config, not a title
}

logline("parsed. from='$email' type=" . ($is_essay ? 'essay' : ($looks_like_reply ? 'reply?' : 'post')) . " poll=" . ($poll_options ? implode(' | ', $poll_options) : 'none'));

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
$mysqli->set_charset(site_charset($cfg));

// ---- Which post is this a reply to? ----
// Replies always attach to the top-level post, so a reply to a reply joins the
// same thread rather than nesting.
$parent_id = null;   // thread root, where a reply gets filed
$match_id  = null;   // the exact message replied to, which is what delete targets
$match_how = null;

$root_of = function ($row) {
    return $row['parent_id'] !== null ? (int)$row['parent_id'] : (int)$row['id'];
};

if ($looks_like_reply && $reply_to_ids) {
    $in = implode(',', array_fill(0, count($reply_to_ids), '?'));
    $q = $mysqli->prepare("SELECT id, parent_id, email_message_id FROM messages WHERE email_message_id IN ($in)");
    $q->bind_param(str_repeat('s', count($reply_to_ids)), ...$reply_to_ids);
    $q->execute();
    $found = [];
    foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $found[$row['email_message_id']] = $row;
    $q->close();
    foreach ($reply_to_ids as $rid) {           // In-Reply-To first, then nearest References
        if (isset($found[$rid])) {
            $match_id = (int)$found[$rid]['id']; $parent_id = $root_of($found[$rid]); $match_how = 'message-id'; break;
        }
    }
}

if ($looks_like_reply && $parent_id === null && $thread_key && $thread_is_reply) {
    $q = $mysqli->prepare("SELECT id, parent_id FROM messages WHERE thread_key = ? ORDER BY received_at ASC LIMIT 1");
    $q->bind_param('s', $thread_key);
    $q->execute();
    if ($row = $q->get_result()->fetch_assoc()) {
        $match_id = (int)$row['id']; $parent_id = $root_of($row); $match_how = 'thread-index';
    }
    $q->close();
}

if ($looks_like_reply && $parent_id === null && $quoted_original !== '') {
    // Posts sent before ids were stored. Newest first, so a repeated phrase
    // matches the most recent post that used it.
    $res = $mysqli->query("SELECT id, parent_id, message FROM messages WHERE deleted_at IS NULL ORDER BY received_at DESC LIMIT 300");
    while ($row = $res->fetch_assoc()) {
        if (reply_quote_matches($quoted_original, $row['message'])) {
            $match_id = (int)$row['id']; $parent_id = $root_of($row); $match_how = 'quoted-text'; break;
        }
    }
}

// ---- "delete" ----
// Hides the post rather than destroying it: matching a reply to an old post is
// partly fuzzy, so a wrong match must stay recoverable. Purge for real later
// with the command in SETUP.md.
if ($is_delete) {
    if ($match_id === null) {
        logline("delete requested but no matching post was found — nothing changed, nothing posted");
        $mysqli->close();
        exit(0);
    }
    // Hiding a post hides its replies too; hiding a reply affects only itself.
    $ds = $mysqli->prepare(
        "UPDATE messages SET deleted_at = NOW()
         WHERE (id = ? OR parent_id = ?) AND deleted_at IS NULL"
    );
    $ds->bind_param("ii", $match_id, $match_id);
    $ds->execute();
    $hidden = $ds->affected_rows;
    $ds->close();
    logline("deleted post $match_id (matched by $match_how) — $hidden row(s) hidden");
    $mysqli->close();
    exit(0);
}

if ($looks_like_reply) {
    logline($parent_id !== null
        ? "reply to post $parent_id (matched by $match_how)"
        : "looked like a reply but no matching post was found — posting as a new post");
}

// An Outlook reply shouldn't claim a conversation key it inherited; store the key
// only on the post that started it, so later replies find the right root.
$store_thread_key = ($parent_id === null) ? $thread_key : null;

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
    "INSERT INTO messages
       (sender_name, sender_email, subject, message, image_url, parent_id, email_message_id, thread_key, received_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
);
$stmt->bind_param("sssssiss", $name, $email, $subject, $clean_message, $image_url, $parent_id, $own_msg_id, $store_thread_key);
$stmt->execute();
$message_id = $mysqli->insert_id;
$stmt->close();

if ($poll_options && $message_id && $parent_id === null) {
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

#!/usr/bin/php -q
<?php
ini_set('display_errors', 0);
error_reporting(0);



// Read entire email from stdin
$email_content = file_get_contents('php://stdin');

//Error and Dubug logging
$log = '/home/vuc923ya50qu/mail/alexhixson.zerofour.tech/message/mail_debug.log';
file_put_contents($log, date('Y-m-d H:i:s') . " — script started\n", FILE_APPEND);

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $log);
error_reporting(E_ALL);

// Split email into lines
$lines = explode("\n", $email_content);

// Initialize variables
$name = "";
$email = "";
$to = "";
$subject = "";
$headers = "";
$message = "";
$is_header = true;

// Process headers and body
foreach ($lines as $line) {
    if ($is_header) {
        $headers .= $line . "\n";

        if (preg_match("/^Subject: (.*)/i", $line, $matches)) {
            $subject = trim($matches[1]);
        } elseif (preg_match("/^From: (.*)/i", $line, $matches)) {
            $fromLine = trim($matches[1]);

            // Handle "Name <email>" or just "email"
            if (preg_match('/(.*)<([^>]+)>/', $fromLine, $m)) {
                $name = trim($m[1], "\"' ");
                $email = strtolower(trim($m[2]));
            } else {
                // If only email is provided, infer name from before the '@'
                $email = strtolower(trim($fromLine));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $name = substr($email, 0, strpos($email, '@'));
                } else {
                    $name = '';
                }
            }

        } elseif (preg_match("/^To: (.*)/i", $line, $matches)) {
            $to = trim($matches[1]);
        }

        if (trim($line) == "") {
            $is_header = false;
        }
    } else {
        $message .= $line . "\n";
    }
}

// Extract content type and boundary
$content_type = "";
$boundary = "";
if (preg_match('/Content-Type: (.*?);/i', $headers, $matches)) {
    $content_type = $matches[1];
    if (preg_match('/boundary="?(.*?)"?(\s|$)/i', $headers, $matches)) {
        $boundary = $matches[1];
    }
}

// For multipart emails, extract parts
$plain_text = "";
$html_content = "";

$content_type = "";
$boundary = "";
if (preg_match('/Content-Type: (.*?);/i', $headers, $matches)) {
    $content_type = trim($matches[1]);
    if (preg_match('/boundary="?(.*?)"?(;|\s|$)/i', $headers, $matches)) {
        $boundary = trim($matches[1]);
    }
}

// Initialize
$plain_text = "";
$html_content = "";

// Handle multipart messages
if ($boundary && stripos($content_type, 'multipart/') === 0) {
    $parts = explode("--" . $boundary, $message);

    foreach ($parts as $part) {
        if (trim($part) === '' || strpos($part, 'Content-Type') === false) continue;

        // Plain text part
        if (stripos($part, 'Content-Type: text/plain') !== false) {
            $plain_text = preg_replace('/^.*?\r?\n\r?\n/s', '', $part);
            if (stripos($part, 'Content-Transfer-Encoding: quoted-printable') !== false) {
                $plain_text = quoted_printable_decode($plain_text);
            }
            $plain_text = trim($plain_text);
        }

        // HTML part
        if (stripos($part, 'Content-Type: text/html') !== false) {
            $html_content = preg_replace('/^.*?\r?\n\r?\n/s', '', $part);
            if (stripos($part, 'Content-Transfer-Encoding: quoted-printable') !== false) {
                $html_content = quoted_printable_decode($html_content);
            }
            $html_content = trim(strip_tags($html_content));
        }
    }
} else {
    // Single-part message
    $plain_text = $message;
    if (stripos($headers, 'Content-Transfer-Encoding: quoted-printable') !== false) {
        $plain_text = quoted_printable_decode($plain_text);
    }
}

// Choose what to store
$clean_message = !empty($plain_text) ? $plain_text : $html_content;

// Optional cleanup for extra whitespace
$clean_message = preg_replace('/\s+/', ' ', trim($clean_message));


// ---- Load secrets from external file ----
$secrets = include('/home/vuc923ya50qu/secrets.php');

// ---- Decide whether to accept message ----
// Condition: sender is in allowed list OR subject contains token (case-insensitive)
$allowedSenders = array_map('strtolower', $secrets['allowed_senders']);
$subjectToken = $secrets['subject_token'] ?? '';
$tokenInSubject = ($subjectToken !== '') && (strpos($subject, $subjectToken) !== false);
$emailAllowed = ($email !== '' && in_array($email, $allowedSenders, true));

if (! $emailAllowed && ! $tokenInSubject) {
    $invalid_access = '/home/vuc923ya50qu/mail/alexhixson.zerofour.tech/message/invalid_access.log';
    file_put_contents($invalid_access, date('Y-m-d H:i:s') . " - Rejected: sender='$email', subject='$subject'\n", FILE_APPEND);
    exit(0);
}

// ---- If token present in subject, remove it (case-sensitive) before saving ----
if ($tokenInSubject) {
    // remove all exact (case-sensitive) instances of token
    $subject = str_replace($subjectToken, '', $subject);
    $subject = trim(preg_replace('/\s+/', ' ', $subject));
}


// ---- Connect to the database ----
$host = $secrets['db']['host'];
$dbname = $secrets['db']['dbname'];
$user = $secrets['db']['user'];
$pass = $secrets['db']['pass'];
$mysqli = new mysqli($host, $user, $pass, $dbname);

if ($mysqli->connect_error) {
    // silently fail so email doesn’t bounce
    exit(0);
}

// ---- Insert into database ----
$stmt = $mysqli->prepare("INSERT INTO messages (sender_name, sender_email, subject, message, received_at)
                        VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("ssss", $name, $email, $subject, $plain_text);
$stmt->execute();

$stmt->close();
$mysqli->close();

exit(0);
?>

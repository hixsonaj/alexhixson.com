<?php
// mail_reply.php — helpers for turning an emailed reply into a threaded post.
//
// LIVES AT THE ACCOUNT ROOT next to site_config.php, and is loaded by
// message.php. Pure functions only: no database, no I/O, so it can be tested on
// its own (see tests/test_mail_reply.php).
//
// A reply is matched to its post, in order of reliability:
//   1. In-Reply-To / References   — standard headers every client sends
//   2. Thread-Index               — Outlook's own conversation marker
//   3. The quoted original text   — for posts sent before ids were stored

/** All message ids in a header value, without angle brackets, lowercased. */
function reply_ids_from_header($raw) {
    if (!$raw) return [];
    preg_match_all('/<([^<>\s]+)>/', $raw, $m);
    $ids = $m[1];
    // Some clients omit the brackets on a single id.
    if (!$ids && preg_match('/^\s*([^\s<>]+@[^\s<>]+)\s*$/', $raw, $one)) {
        $ids = [$one[1]];
    }
    return array_values(array_unique(array_map('strtolower', $ids)));
}

/**
 * Outlook's Thread-Index is base64: 22 bytes identifying the conversation, then
 * 5 more bytes per reply. Returns the conversation key (base64 of the first 22
 * bytes) and whether this message is itself a reply (longer than 22 bytes).
 */
function reply_thread_index($raw) {
    $bin = base64_decode(preg_replace('/\s+/', '', (string)$raw), true);
    if ($bin === false || strlen($bin) < 22) return [null, false];
    return [base64_encode(substr($bin, 0, 22)), strlen($bin) > 22];
}

/** True for a subject that looks like a reply: "Re:", "RE:", "Re[2]:", "AW:", "SV:". */
function reply_subject_is_reply($subject) {
    return (bool)preg_match('/^\s*(re|aw|sv|antw)(\[\d+\])?\s*:/i', (string)$subject);
}

/**
 * True when the subject is just "delete" — the instruction to remove the post
 * being replied to. Any Re:/Fwd: prefixes the mail client added are ignored, so
 * editing the subject to "delete" is enough.
 */
function reply_subject_is_delete($subject) {
    $s = trim((string)$subject);
    // Strip any stack of reply/forward prefixes: "Re: Fwd: delete"
    while (preg_match('/^\s*(re|aw|sv|antw|fwd?|wg)(\[\d+\])?\s*:\s*(.*)$/is', $s, $m)) {
        $s = $m[3];
    }
    return (bool)preg_match('/^(delete|remove)[.!]?$/i', trim($s));
}

/** Collapse whitespace and quote styles so text from different sources compares equal. */
function reply_normalize($text) {
    $t = str_replace(["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{00A0}"], ["'", "'", '"', '"', ' '], (string)$text);
    $t = preg_replace('/\s+/u', ' ', $t);
    return mb_strtolower(trim($t));
}

/**
 * Split a reply body into [new text, quoted original].
 *
 * Cuts at the first marker of quoted history. Recognizes:
 *   - Outlook's underscore rule, then a From:/Sent: header block
 *   - "-----Original Message-----"
 *   - a bare From: … Sent:/Date: header block (Outlook for Mac and iOS)
 *   - "On <date>, <name> wrote:", including when wrapped onto two lines
 *   - a run of "> " quoted lines
 * and drops mobile sign-offs ("Sent from my iPhone", "Get Outlook for iOS")
 * sitting directly above the history.
 *
 * The quoted part is returned without its header block or "> " prefixes, for
 * matching against existing posts.
 */
function reply_split_quote($body) {
    $lines = preg_split('/\r\n|\r|\n/', (string)$body);
    $n = count($lines);
    $cut = null;       // first line of quoted history
    $quoteStart = null; // first line of the original's actual text

    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        $t = trim($line);

        // Outlook's rule: a long run of underscores, then the header block.
        if (preg_match('/^_{8,}$/', $t)) {
            $cut = $i;
            $quoteStart = reply_skip_header_block($lines, $i + 1);
            break;
        }

        if (preg_match('/^-{2,}\s*Original Message\s*-{2,}$/i', $t)) {
            $cut = $i;
            $quoteStart = reply_skip_header_block($lines, $i + 1);
            break;
        }

        // Bare header block: From: followed within a few lines by Sent: or Date:
        if (preg_match('/^\*?From:\*?\s+\S/i', $t)) {
            for ($j = $i + 1; $j < min($n, $i + 5); $j++) {
                if (preg_match('/^\*?(Sent|Date):\*?\s+\S/i', trim($lines[$j]))) {
                    $cut = $i;
                    $quoteStart = reply_skip_header_block($lines, $i);
                    break 2;
                }
            }
        }

        // "On <date>, <name> wrote:" — possibly wrapped across two lines.
        if (preg_match('/^On\s.+\swrote:$/i', $t)) {
            $cut = $i;
            $quoteStart = $i + 1;
            break;
        }
        if (preg_match('/^On\s.+/i', $t) && $i + 1 < $n && preg_match('/wrote:$/i', trim($lines[$i + 1]))) {
            $cut = $i;
            $quoteStart = $i + 2;
            break;
        }

        // A block of "> " quoted lines.
        if (preg_match('/^>/', $t)) {
            $cut = $i;
            $quoteStart = $i;
            break;
        }
    }

    if ($cut === null) {
        return [trim((string)$body), ''];
    }

    // Drop blank lines and mobile sign-offs directly above the history.
    $end = $cut;
    while ($end > 0) {
        $prev = trim($lines[$end - 1]);
        if ($prev === '' || preg_match('/^(Sent from my \w+|Get Outlook for (iOS|Android)|Sent from Outlook for (iOS|Android)|Sent from Mail for Windows)\b/i', $prev)) {
            $end--;
            continue;
        }
        break;
    }

    $reply = trim(implode("\n", array_slice($lines, 0, $end)));

    $quoted = [];
    for ($k = $quoteStart; $k < $n; $k++) {
        $quoted[] = preg_replace('/^\s*(>\s?)+/', '', $lines[$k]);
    }

    // Apple Mail puts its "On … wrote:" line inside the quote. Drop it, and a
    // header block if one follows, so the quote starts at the original's text.
    $q = 0;
    while ($q < count($quoted) && trim($quoted[$q]) === '') $q++;
    if ($q < count($quoted) && preg_match('/^On\s.+wrote:$/i', trim($quoted[$q]))) {
        $q++;
    }
    $quoted = array_slice($quoted, reply_skip_header_block($quoted, $q));

    return [$reply, trim(implode("\n", $quoted))];
}

/** Index of the first line after a From:/Sent:/To:/Subject: header block. */
function reply_skip_header_block($lines, $from) {
    $n = count($lines);
    $i = $from;
    while ($i < $n && trim($lines[$i]) === '') $i++;
    $sawHeader = false;
    while ($i < $n && preg_match('/^\*?(From|Sent|Date|To|Cc|Subject):\*?(\s|$)/i', trim($lines[$i]))) {
        $sawHeader = true;
        $i++;
    }
    return $sawHeader ? $i : $from;
}

/**
 * Whether a quoted original plausibly is a given post. Compares the start of
 * both, normalized, so trailing signatures and wrapping don't matter.
 */
function reply_quote_matches($quoted, $postText, $prefixChars = 60) {
    $q = reply_normalize($quoted);
    $p = reply_normalize($postText);
    if ($q === '' || $p === '') return false;
    $len = min($prefixChars, mb_strlen($p));
    if ($len < 12) {
        // Very short posts ("Test") match only exactly, to avoid false threads.
        return $q === $p || mb_strpos($q, $p . ' ') === 0;
    }
    return mb_substr($q, 0, $len) === mb_substr($p, 0, $len);
}

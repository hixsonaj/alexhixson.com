<?php
// php "Server Side/tests/test_mail_reply.php"
require __DIR__ . '/../mail_reply.php';

$fail = 0;
function check($label, $got, $want) {
    global $fail;
    $ok = $got === $want;
    if (!$ok) $fail++;
    echo ($ok ? "PASS " : "FAIL ") . $label . "\n";
    if (!$ok) { echo "   got : " . var_export($got, true) . "\n   want: " . var_export($want, true) . "\n"; }
}
function split_case($label, $body, $wantReply, $wantQuoteStart) {
    [$reply, $quote] = reply_split_quote($body);
    check("$label: reply", $reply, $wantReply);
    check("$label: quote starts with original", mb_substr($quote, 0, mb_strlen($wantQuoteStart)), $wantQuoteStart);
}

echo "--- quote stripping ---\n";
split_case('Outlook web/desktop',
"Agreed, going back next week.

________________________________
From: Alex Hixson <hixsonaj@hotmail.com>
Sent: Monday, September 14, 2026 8:30 PM
To: alexfeed@zerofour.tech <alexfeed@zerofour.tech>
Subject:

Me and my slimes
", "Agreed, going back next week.", "Me and my slimes");

split_case('Outlook iOS with sign-off',
"Honestly yes

Get Outlook for iOS<https://aka.ms/o0ukef>
________________________________
From: Alex Hixson <hixsonaj@hotmail.com>
Sent: Monday, September 14, 2026 8:31:02 PM
To: alexfeed@zerofour.tech <alexfeed@zerofour.tech>
Subject: Re: Poll: yes, no

Is the blog link in the instagram bio cringe be honest
", "Honestly yes", "Is the blog link in the instagram bio cringe");

split_case('Original Message separator',
"Update: it worked.
-----Original Message-----
From: Alex Hixson <hixsonaj@hotmail.com>
Sent: Monday, September 14, 2026 8:30 PM
To: alexfeed@zerofour.tech
Subject: 

Trying the new printer
", "Update: it worked.", "Trying the new printer");

split_case('bare header block (Outlook Mac)',
"Second thought, no.

From: Alex Hixson <hixsonaj@hotmail.com>
Date: Monday, September 14, 2026 at 8:30 PM
To: alexfeed@zerofour.tech <alexfeed@zerofour.tech>
Subject: 

Buying a boat
", "Second thought, no.", "Buying a boat");

split_case('Gmail, wrapped attribution',
"Totally agree

On Mon, Sep 14, 2026 at 8:30 PM Alex Hixson <
hixsonaj@hotmail.com> wrote:

> Me and my slimes
>
", "Totally agree", "Me and my slimes");

split_case('Apple Mail, attribution inside quote',
"Nice

Sent from my iPhone

> On Sep 14, 2026, at 8:30 PM, Alex Hixson <hixsonaj@hotmail.com> wrote:
>
> Me and my slimes
", "Nice", "Me and my slimes");

split_case('multi-paragraph reply kept whole',
"First paragraph.

Second paragraph with a [link](example.com).

________________________________
From: X <x@y.com>
Sent: today

Original
", "First paragraph.

Second paragraph with a [link](example.com).", "Original");

[$r, $q] = reply_split_quote("No history here, they deleted it.\n");
check('no quote: reply is whole body', $r, 'No history here, they deleted it.');
check('no quote: quote empty', $q, '');

[$r, $q] = reply_split_quote("I think 5 > 3 on most days\nand that's that");
check('inline > is not a quote', $r, "I think 5 > 3 on most days\nand that's that");

[$r, $q] = reply_split_quote("Moved from: Seattle\nto Bothell");
check('ordinary From: text not a header block', $r, "Moved from: Seattle\nto Bothell");

echo "--- headers ---\n";
check('References, many ids', reply_ids_from_header("<A1@x.com>\r\n <b2@Y.com> <c3@z.com>"), ['a1@x.com', 'b2@y.com', 'c3@z.com']);
check('In-Reply-To without brackets', reply_ids_from_header("abc@outlook.com"), ['abc@outlook.com']);
check('empty header', reply_ids_from_header(null), []);

$root = base64_encode(str_repeat("\x01", 22));
$reply = base64_encode(str_repeat("\x01", 22) . "\x09\x08\x07\x06\x05");
check('thread index: original', reply_thread_index($root), [$root, false]);
check('thread index: reply shares key', reply_thread_index($reply), [$root, true]);
check('thread index: garbage', reply_thread_index('!!not base64!!'), [null, false]);

check('Re: subject', reply_subject_is_reply('Re: Essay: Title'), true);
check('RE[2]: subject', reply_subject_is_reply('RE[2]: hi'), true);
check('not a reply subject', reply_subject_is_reply('Essay: Rebuilding my bike'), false);

echo "--- matching quoted text to a post ---\n";
check('curly vs straight quotes', reply_quote_matches("Y'all rock with the LinkedIn and zero four profile pic? I gotta go to New York", "Y’all rock with the LinkedIn and zero four profile pic? I gotta go to New York"), true);
check('rewrapped long post', reply_quote_matches("Would people even want a renewed\nvolume by speed app? I had Claude", "Would people even want a renewed volume by speed app? I had Claude build it"), true);
check('different post', reply_quote_matches("Networking the fuck out of these guys", "Me and my slimes"), false);
check('short post exact', reply_quote_matches("Test", "Test"), true);
check('short post not a prefix of longer', reply_quote_matches("Testing the new printer", "Test"), false);

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);

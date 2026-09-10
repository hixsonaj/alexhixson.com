<?php
// Template for secrets.php — this file IS committed; secrets.php is not.
// Copy to the account root as secrets.php and fill in real values.
//
//     /home/<user>/secrets.php
//
// Adding a site is one entry here plus a database. No code changes.

return [
    'sites' => [

        'example.zerofour.tech' => [
            'db' => [
                'host'   => 'zerofour.tech',
                'dbname' => '',
                'user'   => '',
                'pass'   => ''
            ],

            // Lowercase addresses allowed to post by email.
            'allowed_senders' => [
                'someone@example.com',
            ],

            // Escape hatch: a message whose subject contains this token is
            // accepted from any address, and the token is stripped before the
            // post is saved. Use a different one per site, or set '' to disable.
            'subject_token' => '',
        ],

    ],
];

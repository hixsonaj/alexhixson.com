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
            // Optional: other domains pointed at this site, like its .com.
            // Leave off the www. — it's stripped before matching.
            'aliases' => ['example.com'],
            'db' => [
                'host'   => 'zerofour.tech',
                'dbname' => '',
                'user'   => '',
                'pass'   => '',
                // Optional. Defaults to utf8mb4, which is right for any database
                // created from schema.sql. Only set 'latin1' for a legacy
                // database whose rows were written without a declared charset.
                // 'charset' => 'utf8mb4',
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

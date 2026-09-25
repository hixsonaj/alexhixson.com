#!/usr/bin/php -q
<?php
// backfill_vote_ips.php — fill poll_votes.ip_address for votes cast before the
// address was stored, by matching each vote's timestamp against the webserver
// access log and confirming the candidate hashes to the stored ip_hash.
//
//   php backfill_vote_ips.php <site-domain> [--write]
//
// Without --write it only reports. A candidate is accepted only when its
// SHA-256 equals the stored hash, so a wrong address can't be written.

$domain = $argv[1] ?? '';
$write  = in_array('--write', $argv, true);
if ($domain === '') { fwrite(STDERR, "usage: php backfill_vote_ips.php <site-domain> [--write]\n"); exit(1); }

$root = __DIR__;
require_once $root . '/site_config.php';
$cfg = site_secrets($domain, $root . '/secrets.php');
if (!$cfg) { fwrite(STDERR, "no config for $domain\n"); exit(1); }

$db = $cfg['db'];
$conn = new mysqli($db['host'], $db['user'], $db['pass'], $db['dbname']);
if ($conn->connect_error) { fwrite(STDERR, "db: {$conn->connect_error}\n"); exit(1); }

// Every access-log line for a vote submission, keyed by timestamp.
$byTime = [];
$logs = array_merge(
    glob("$root/access-logs/$domain*") ?: [],
    glob("$root/logs/$domain*") ?: []
);
foreach ($logs as $path) {
    $fh = substr($path, -3) === '.gz' ? gzopen($path, 'r') : fopen($path, 'r');
    if (!$fh) continue;
    $read = substr($path, -3) === '.gz' ? 'gzgets' : 'fgets';
    while (($line = $read($fh)) !== false) {
        if (strpos($line, 'submit_vote.php') === false) continue;
        if (!preg_match('/^(\S+) \S+ \S+ \[([^\]]+)\]/', $line, $m)) continue;
        // Apache's "24/Sep/2026:18:44:47 -0700" — strtotime can't read a slashed
        // date with a month name, so parse the format explicitly.
        $dt = DateTime::createFromFormat('d/M/Y:H:i:s O', $m[2]);
        if (!$dt) continue;
        // Keyed by absolute time, so PHP's timezone and MySQL's never have to agree.
        $byTime[$dt->getTimestamp()][] = $m[1];
    }
    $read === 'gzgets' ? gzclose($fh) : fclose($fh);
}
echo "  scanned " . count($logs) . " log file(s), " . count($byTime) . " vote request timestamp(s)\n";

// UNIX_TIMESTAMP lets MySQL resolve voted_at in its own timezone.
$rows = $conn->query("SELECT id, ip_hash, voted_at, UNIX_TIMESTAMP(voted_at) AS ts FROM poll_votes WHERE ip_address IS NULL");
$hit = $miss = 0;
while ($v = $rows->fetch_assoc()) {
    $found = null;
    // Allow a couple of seconds either way: the log records when the request
    // arrived, the row when the insert ran.
    for ($off = -2; $off <= 2 && $found === null; $off++) {
        foreach ($byTime[(int)$v['ts'] + $off] ?? [] as $ip) {
            if (hash('sha256', $ip) === $v['ip_hash']) { $found = $ip; break; }
        }
    }
    if ($found === null) { $miss++; echo "  vote {$v['id']} ({$v['voted_at']}): no match in logs\n"; continue; }
    $hit++;
    echo "  vote {$v['id']} ({$v['voted_at']}): $found" . ($write ? " — written" : " — dry run") . "\n";
    if ($write) {
        $u = $conn->prepare("UPDATE poll_votes SET ip_address = ? WHERE id = ?");
        $u->bind_param("si", $found, $v['id']);
        $u->execute();
        $u->close();
    }
}
echo "  matched $hit, unmatched $miss" . ($write ? "" : "  (dry run — pass --write to save)") . "\n";

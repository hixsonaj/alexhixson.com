<?php
// site_config.php — works out which site a script is running for, and loads that
// site's secrets.
//
// LIVES AT THE ACCOUNT ROOT, next to secrets.php:
//     /home/<user>/site_config.php
// Not in public_html, so it can never be fetched over HTTP. One copy serves every
// site — there is no per-site version to keep in sync.
//
// Web scripts identify the site by Host header. The mail pipe has no Host, so it
// passes its domain in explicitly.

/** Account root. This file sits in it, so no path arithmetic and nothing hardcoded. */
function account_root() {
    return __DIR__;
}

/** The domain being requested: lowercased, no port, no leading www. */
function current_domain() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = strtolower(preg_replace('/:\d+$/', '', $host));
    return preg_replace('/^www\./', '', $host);
}

/** Absolute base URL for this request, e.g. https://leahhixson.zerofour.tech */
function site_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . current_domain();
}

/**
 * Config for one site, or null if the domain has no entry. Callers must treat
 * null as an error and stop.
 *
 * Deliberately does NOT fall back to a default site. A missing entry means a
 * misconfigured domain, and quietly serving another site's database is the exact
 * failure this whole setup exists to prevent.
 *
 * Also accepts the old single-site secrets.php shape, so code and secrets can be
 * deployed in either order with no downtime.
 */
function site_secrets($domain = null, $secrets_path = null) {
    $path = $secrets_path ?: account_root() . '/secrets.php';
    if (!is_readable($path)) return null;

    $all = include($path);
    if (!is_array($all)) return null;

    if (!isset($all['sites'])) return $all;   // legacy flat shape

    $domain = $domain !== null ? strtolower($domain) : current_domain();
    return $all['sites'][$domain] ?? null;
}

/** Emit a JSON error and stop. Never leaks config details to the client. */
function site_fail($code, $message) {
    http_response_code($code);
    echo json_encode(["error" => $message]);
    exit;
}

/** Connect to this site's database, or fail with JSON. */
function site_db($cfg) {
    if (!$cfg || empty($cfg['db'])) {
        site_fail(500, "This domain is not configured");
    }
    $db = $cfg['db'];
    $conn = new mysqli($db['host'], $db['user'], $db['pass'], $db['dbname']);
    if ($conn->connect_error) {
        site_fail(500, "Database connection failed");
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

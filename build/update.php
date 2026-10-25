<?php
/** @noinspection PhpLanguageLevelInspection */

// Server side script, runs on PHP 8.3+

require_once($_SERVER["DOCUMENT_ROOT"] . "/shared_scripts/iptv_utils.php");

/**
 * Replace control chars so a request value can't break a log line
 */
function log_safe(string $value): string
{
    return preg_replace('/[\x00-\x1F\x7F]/', ' ', $value);
}

/**
 * Write the log, send an error status and stop
 */
function send_error(int $code, string $logbuf): never
{
    write_to_log($logbuf, 'error.log');
    http_response_code($code);
    exit;
}

$ip = get_ip();
$country = IP2Country($ip);
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$logbuf = "========================================" . PHP_EOL;
$logbuf .= "date       : " . date("Y.m.d H:i:s") . PHP_EOL;
$logbuf .= "ip         : $ip ( $country )" . PHP_EOL;
$logbuf .= "user_agent : " . log_safe($user_agent) . PHP_EOL;

$revision = 0;
if (preg_match("/firmware_version:\s+([0-9_rb]+)/", $user_agent, $m)
    && preg_match('/.+_[rb](\d{2})/', $m[1], $m)) {
    $revision = (int)$m[1];
}

$url_params = parse_url((string)getenv("REQUEST_URI")) ?: [];
$path = $url_params['path'] ?? '';
if (isset($url_params['query'])) {
    parse_str($url_params['query'], $params);
    $dune_auth = $params['dune_auth'] ?? null;
    if (is_string($dune_auth)) {
        $values = explode(' ', $dune_auth);
        $logbuf .= "serial     : " . log_safe($values[2] ?? '') . PHP_EOL;
        $logbuf .= "int serial : " . log_safe($values[3] ?? '') . PHP_EOL;
    }
}

$info = pathinfo($path);
$ext = $info['extension'] ?? '';

if ($revision === 0) {
    send_error(403, $logbuf . "Unknown version" . PHP_EOL);
}

if ($ext !== 'xml' && $ext !== 'gz') {
    send_error(404, $logbuf . "Unknown file requested: " . log_safe($path) . PHP_EOL);
}

[$log_name, $dir] = match (true) {
    $revision > 21 => ["update_new.log", "current"],
    $revision > 20 => ["update_mid.log", "mid"],
    default => ["update_old.log", "old"],
};
$result_path = "./$dir/" . $info['basename'];

$logbuf .= "url path   : " . log_safe($path) . PHP_EOL;
$logbuf .= "new path   : " . log_safe($result_path) . PHP_EOL;

if (!is_file($result_path)) {
    send_error(404, $logbuf . "File not found" . PHP_EOL);
}

http_response_code(200);
if ($ext === 'gz') {
    write_to_log($logbuf, $log_name);
    header("Content-Length: " . filesize($result_path));
    header("Content-Type: application/octet-stream");
} else {
    header("Content-Type: text/xml");
}

header("Cache-Control: no-cache");
header("Pragma: no-cache");
header("Expires: 0");

readfile($result_path);

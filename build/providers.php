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
 * Send an error status with a JSON body and stop
 */
function send_error(int $code, string $message = ''): never
{
    http_response_code($code);
    if ($message !== '') {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(['error' => $message]);
    }
    exit;
}

/**
 * Query parameter as string, a missing or array value gives ''
 */
function get_param(array $params, string $name): string
{
    $value = $params[$name] ?? '';
    return is_string($value) ? $value : '';
}

$white_list = is_file("whitelist.txt")
    ? array_values(array_filter(array_map('trim', file("whitelist.txt", FILE_IGNORE_NEW_LINES) ?: [])))
    : [];

$params = [];
$query = parse_url((string)getenv("REQUEST_URI"), PHP_URL_QUERY);
if (is_string($query)) {
    parse_str($query, $params);
}

$version = get_param($params, 'ver');
if ($version === '') {
    send_error(404, "This version is not supported");
}

$time = time();
$date = date("Y.m.d H:i:s");
$ip = get_ip();
$country = IP2Country($ip);
$model = strtolower(get_param($params, 'model'));
$serial = get_param($params, 'serial');
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$firmware = get_param($params, 'firmware');
if ($firmware === '' && preg_match("/firmware_version:\s+([0-9_rb]+)/", $user_agent, $m)) {
    $firmware = $m[1];
}

$revision = '';
$rev = 0;
if ($firmware !== '' && preg_match('/.+_([rb]\d{2})/', $firmware, $m)) {
    $revision = $m[1];
    $rev = (int)substr($revision, 1);
}

$request = (string)getenv("REQUEST_URI");

$logbuf = "========================================" . PHP_EOL;
$logbuf .= "date       : $date" . PHP_EOL;
$logbuf .= "url        : " . log_safe($request) . PHP_EOL;
$logbuf .= "ip         : $ip ( $country )" . PHP_EOL;
$logbuf .= "version    : " . log_safe($version) . PHP_EOL;
$logbuf .= "model      : " . log_safe($model) . PHP_EOL;
$logbuf .= "firmware   : " . log_safe($firmware) . PHP_EOL;
$logbuf .= "serial     : " . log_safe($serial) . PHP_EOL;
$logbuf .= "user_agent : " . log_safe($user_agent) . PHP_EOL;

if ($revision === '') {
    $logbuf .= "Unknown version" . PHP_EOL;
    write_to_log($logbuf, 'error.log');
    send_error(403);
}

if ($rev < 11) {
    write_to_log($logbuf, 'unsupported.log');
    send_error(404, "This version is not supported");
}

$DB = new db_driver();
$DB->obj['sql_database'] = IPTV_DATABASE;
$DB->obj['sql_user'] = IPTV_USER;
$DB->obj['sql_pass'] = IPTV_PASSWORD;

if ($DB->connect()) {
    if ($serial !== '') {
        $DB->insert_or_update_table([
            'model' => $model,
            'firmware' => $firmware,
            'revision' => $revision,
            'serial' => $serial,
            'time' => $time,
            'date' => $date,
            'version' => $version,
            'ip' => $ip,
            'country' => $country,
        ], 'statistics');
        if (!empty($DB->error)) {
            write_to_log("database query error $DB->error", 'error.log');
        }
    } else {
        write_to_log("bad url query " . log_safe($request), 'error.log');
    }
    $DB->close_db();
} else {
    write_to_log("can't connect to database", 'error.log');
}

$name = '';
$major = 0;
if (preg_match('/^(\d+)\.(\d+)/', $version, $m)) {
    $name = "providers_$m[1].$m[2].json";
    $major = (int)$m[1];
}

$disabled = "providers_disabled.json";
if ($name === '' || !file_exists($name)
    || (!in_array($serial, $white_list, true)
        && (($major === 5 && $rev > 16) || ($major === 6 && $rev > 21)))) {
    $logbuf .= "disabled   : yes" . PHP_EOL;
    $name = $disabled;
}

write_to_log($logbuf, match (true) {
    $rev < 17 => 'old.log',
    $rev < 22 => 'mid.log',
    $name === $disabled => 'disabled.log',
    default => 'providers.log',
});

http_response_code(200);
header("Content-Type: application/json; charset=utf-8");
readfile($name);

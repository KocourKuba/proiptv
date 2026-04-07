<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/shared_scripts/iptv_utils.php");

$white_list = [];
$file = file_get_contents("whitelist.txt");
foreach (explode(PHP_EOL, $file) as $l) {
    if (!empty($l)) {
        $white_list[] = trim($l);
    }
}

$url_params = parse_url(getenv("REQUEST_URI"));
if (isset($url_params['query'])) {
    parse_str($url_params['query'], $params);
}

$name = '';
if (!isset($params['ver'])) {
    header("HTTP/1.1 404 Not found");
    echo '["error" : "This version is not supported"]';
    die();
}

$time = time();
$date = date("Y.m.d H:i:s");
$ip = get_ip();
$country = IP2Country($ip);
$version = $params['ver'];
$model =  $params['model'];
$serial = $params['serial'];

$firmware = '';
$revision = '';
$rev = 0;
/** @var array $m */
if (isset($params['firmware'])) {
    $firmware = $params['firmware'];
} else if (preg_match("/firmware_version:\s+([0-9_rb]+)/", $_SERVER['HTTP_USER_AGENT'], $m)) {
    $firmware = $m[1];
}

if (!empty($firmware) && preg_match('/.+_(r\d{2})/', $firmware, $m)) {
    $revision = $m[1];
    $rev = substr($revision, 1);
}

$request = getenv("REQUEST_URI");

$logbuf = "========================================" . PHP_EOL;
$logbuf .= "date       : $date" . PHP_EOL;
$logbuf .= "url        : $request" . PHP_EOL;
$logbuf .= "ip         : $ip ( $country )" . PHP_EOL;
$logbuf .= "version    : $version" . PHP_EOL;
$logbuf .= "model      : $model" . PHP_EOL;
$logbuf .= "firmware   : $firmware" . PHP_EOL;
$logbuf .= "serial     : $serial" . PHP_EOL;
$logbuf .= "user_agent : {$_SERVER['HTTP_USER_AGENT']}" . PHP_EOL;

if (empty($revision)) {
    $logbuf .= "Unknown version";
    write_to_log($logbuf, 'error.log');
    header("HTTP/1.1 403 Forbidden");
    die();
}

if ($rev < 11) {
    write_to_log($logbuf, 'unsupported.log');
    header("HTTP/1.1 404 Not found");
    echo '["error" : "This version is not supported"]';
    die();
}

$DB = new db_driver();
$DB->obj['sql_database'] = IPTV_DATABASE;
$DB->obj['sql_user'] = IPTV_USER;
$DB->obj['sql_pass'] = IPTV_PASSWORD;

if($DB->connect()) {
    $data = [];
    $data['model'] = $model;
    $data['firmware'] = $firmware;
    $data['revision'] = $revision;
    $data['serial'] = $serial;
    $data['time'] = $time;
    $data['date'] = $date;
    $data['version'] = $version;
    $data['ip'] = $ip;
    $data['country'] = $country;
    if (!empty($serial)) {
        $DB->insert_or_update_table($data, 'statistics');
        $error = $DB->error;
        if (!empty($error)) {
            write_to_log("database query error $error", 'error.log');
        }
        $DB->close_db();
    } else {
        write_to_log("bad url query $request", 'error.log');
    }
} else {
    write_to_log("can't connect to database", 'error.log');
}

$ver = explode('.', $version);
if (isset($ver[0], $ver[1])) {
    $name ="providers_$ver[0].$ver[1].json";
}

if (!in_array($serial, $white_list) && (empty($name) || !file_exists($name) || ($rev >= 21 && $rev <= 22 && $ver[0] == 5))) {
    $logbuf .= "disabled   : yes" . PHP_EOL;
    $name = "providers_disabled.json";
}

if ($rev < 21) {
    write_to_log($logbuf, 'old.log');
} else if ($rev < 22) {
    write_to_log($logbuf, 'mid.log');
} else {
    write_to_log($logbuf, 'providers.log');
}

header("HTTP/1.1 200 OK");
header("Content-Type: application/json; charset=utf-8");
readfile($name);

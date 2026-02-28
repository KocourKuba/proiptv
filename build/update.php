<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/shared_scripts/Utils.php");

$ip = get_ip();
$country = IP2Country($ip);

$logbuf = "========================================" . PHP_EOL;
$logbuf .= "date       : " . date("Y.m.d H:i:s") . PHP_EOL;
$logbuf .= "ip         : $ip ( $country )" . PHP_EOL;
$logbuf .= "user_agent : " . $_SERVER['HTTP_USER_AGENT'] . PHP_EOL;

/** @var array $m */
if (preg_match("/firmware_version:\s+([0-9_rb]+)/", $_SERVER['HTTP_USER_AGENT'], $m)) {
    $firmware = $m[1];
}

if (!empty($firmware) && preg_match('/.+_[rb](\d{2})/', $firmware, $m)) {
    $revision = $m[1];
}

$url_params = parse_url(getenv("REQUEST_URI"));
if (isset($url_params['query'])) {
    parse_str($url_params['query'], $params);
    if (isset($params['dune_auth'])) {
        $values = explode(' ', $params['dune_auth']);
        $logbuf .= "serial     : " . $values[2] . PHP_EOL;
        $logbuf .= "int serial : " . $values[3] . PHP_EOL;
    }
}

$info = pathinfo($url_params['path']);
$ext = $info['extension'];

if (empty($revision)) {
    header("HTTP/1.1 403 Forbidden");
    $logbuf .= "Unknown version";
	write_to_log($logbuf, 'error.log');
	die();
}

if ($ext != 'xml' && $ext != 'gz') {
    header("HTTP/1.1 404 Not found");
    $logbuf .= "Unknown file requested: " . $url_params['path'];
	write_to_log($logbuf, 'error.log');
	die();
}

if ($revision > 21) {
    $log_name = "update_new.log";
    $result_path = "./current/" . $info['basename'];
} else if ($revision > 20) {
    $log_name = "update_mid.log";
    $result_path = "./mid/" . $info['basename'];
} else {
    $log_name = "update_old.log";
    $result_path = "./old/" . $info['basename'];
}
$logbuf .= "url path   : " . $url_params['path'] . PHP_EOL;
$logbuf .= "new path   : " . $result_path . PHP_EOL;

header("HTTP/1.1 200 OK");
if ($ext == 'gz') {
    write_to_log($logbuf, $log_name);
    header("Accept-Ranges: bytes");
    header("Content-Length: " . filesize($result_path));
    header("Content-Type: application/octet-stream");
} else if ($ext == 'xml') {
    header("Content-Type: text/xml");
}

header("Pragma: no-cache");
header("Expires: -1");

readfile($result_path);

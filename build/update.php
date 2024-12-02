<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/shared_scripts/crm_settings.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/shared_scripts/MySQL.php");

function write_to_log($value, $logname, $method = 'ab')
{
    if(is_array($value)) {
        $value = print_r($value, true);
    }

    if(substr($value, -1) !== PHP_EOL) {
        $value .= PHP_EOL;
    }

    $fp = fopen($logname, $method);
    if($fp) {
        fwrite($fp, $value);
        fclose($fp);
    }
}

$logbuf = "========================================" . PHP_EOL;
$logbuf .= "user_agent : " . $_SERVER['HTTP_USER_AGENT'] . PHP_EOL;

if (preg_match("/firmware_version:\s+([0-9_rb]+)/", $_SERVER['HTTP_USER_AGENT'], $m)) {
    $firmware = $m[1];
}

if (!empty($firmware) && preg_match('/.+_[rb](\d{2})/', $firmware, $m)) {
    $revision = $m[1];
}

$url_params = parse_url(getenv("REQUEST_URI"));
if (isset($url_params['query'])) {
    parse_str($url_params['query'], $params);
}

if (empty($url_params['path']) || empty($revision)) {
    header("HTTP/1.1 404 Not found");
    echo '["error" : "This version is not supported"]';
} else {
    header("HTTP/1.1 200 OK");
    $info = pathinfo($url_params['path']);
    $new_path = ($revision < 21 ? "old/" : "current/") . $info['basename'];
    echo file_get_contents($new_path);

    $logbuf .= "url path   : " . $url_params['path'] . PHP_EOL;
    $logbuf .= "new path   : " . $new_path . PHP_EOL;
}

write_to_log($logbuf, 'update.log');

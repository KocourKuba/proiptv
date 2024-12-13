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

//Determine if an ip is in a net.
//E.G. 120.120.120.120 in 120.120.0.0/16
//I saw this in another post in this site but don't remember where :P
function isIPInNet($ip, $net, $mask)
{
    $lnet = ip2long($net);
    $lip = ip2long($ip);
    $binnet = str_pad(decbin($lnet), 32, "0", STR_PAD_LEFT);
    $firstpart = substr($binnet, 0, $mask);
    $binip = str_pad(decbin($lip), 32, "0", STR_PAD_LEFT);
    $firstip = substr($binip, 0, $mask);

    return (strcmp($firstpart, $firstip) === 0);
}

//This function check if a ip is in an array of nets (ip and mask)
function isIpInNetArray($theip, $thearray)
{
    $exit_c = false;
    foreach($thearray as $subnet)
    {
        list($net, $mask) = explode("/", $subnet);
        if(isIPInNet($theip, $net, $mask))
        {
            $exit_c = true;
            break;
        }
    }

    return ($exit_c);
}


function get_ip()
{
    // Building the ip array with the HTTP_X_FORWARDED_FOR and REMOTE_ADDR HTTP vars.
    // With this function we get an array where first are the ip's listed in
    // HTTP_X_FORWARDED_FOR and the last ip is the REMOTE_ADDR.

    $ip_private_list = array(
        "10.0.0.0/8",
        "172.16.0.0/12",
        "192.168.0.0/16",
    );

    $ip = "unknown";
    $cad = "";

    if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
    {
        $cad = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else if(!empty($_SERVER['REMOTE_ADDR']))
    {
        $cad .= "," . $_SERVER['REMOTE_ADDR'];
    }

    $ip_array = explode(',', $cad);

    foreach($ip_array as $ip_s)
    {
        if(!empty($ip_s) && !isIPInNetArray($ip_s, $ip_private_list))
        {
            $ip = $ip_s;
            break;
        }
    }

    return ($ip);
}

function IP2Country($ip)
{
    // Detect country by IP
    $iplong = ip2long($ip);
    $query = "SELECT c2code FROM ip2country WHERE ip_from <= $iplong AND ip_to >= $iplong";

    $DB = new db_driver;
    $DB->obj['sql_database'] = CRM_DATABASE;
    $DB->obj['sql_user'] = IPTV_USER;
    $DB->obj['sql_pass'] = IPTV_PASSWORD;

    if($DB->connect()) {
        $DB->query($query);
        $row = $DB->fetch_row();
        $country = $row['c2code'];
    }

    if(empty($country)) {
        $country = 'XX';
    }

    return $country;
}

$ip = get_ip();
$country = IP2Country($ip);

$logbuf = "========================================" . PHP_EOL;
$logbuf .= "date       : " . date("Y.m.d H:i:s") . PHP_EOL;
$logbuf .= "ip         : $ip ( $country )" . PHP_EOL;
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

$new_path = ($revision < 21 ? "./old/" : "./current/") . $info['basename'];
$logbuf .= "url path   : " . $url_params['path'] . PHP_EOL;
$logbuf .= "new path   : " . $new_path . PHP_EOL;

header("HTTP/1.1 200 OK");
if ($ext == 'gz') {
    write_to_log($logbuf, 'update.log');
    header("Accept-Ranges: bytes");
    header("Content-Length: " . filesize($new_path));
    header("Content-Type: application/octet-stream");
} else if ($ext == 'xml') {
    header("Content-Type: text/xml");
}

header("Pragma: no-cache");
header("Expires: -1");

readfile($new_path);

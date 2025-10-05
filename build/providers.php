<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/shared_scripts/crm_settings.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/shared_scripts/MySQL.php");

function write_to_log($value, $logname, $method = 'ab')
{
    if(is_array($value))
    {
        $value = print_r($value, true);
    }

    if(substr($value, -1) !== "\n")
    {
        $value .= "\n";
    }

    $fp = fopen($logname, $method);
    if($fp)
    {
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
        if(!empty($ip_s) && !isIpInNetArray($ip_s, $ip_private_list))
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

    $DB = new db_driver();
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

$url_params = parse_url(getenv("REQUEST_URI"));
if (isset($url_params['query'])) {
    /** @noinspection PhpUndefinedVariableInspection */
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
$name ="providers_$ver[0].$ver[1].json";
if (!file_exists($name) || ($rev >= 21 && $rev <= 22 && $ver[0] == 5)) {
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

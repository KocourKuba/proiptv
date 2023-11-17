<?php

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
        if(!empty($ip_s) && !isIPInNetArray($ip_s, $ip_private_list))
        {
            $ip = $ip_s;
            break;
        }
    }

    return ($ip);
}

$url_params = parse_url(getenv("REQUEST_URI"));
if (isset($url_params['query'])) {
    parse_str($url_params['query'], $params);
}

if (isset($params['ver'])) {
    $ver = explode('.', $params['ver']);
    $name ="providers_$ver[0].$ver[1].json";

    $logbuf = "========================================\n";
    $logbuf .= "date      : " . date("m.d.Y H:i:s") . "\n";
    $logbuf .= "url       : " . getenv("REQUEST_URI") . "\n";
    $logbuf .= "ip        : " . get_ip() . "\n";
    $logbuf .= "ver       : " . $params['ver'] . "\n";
    $logbuf .= "model     : " . $params['model'] . "\n";
    $logbuf .= "serial    : " . $params['serial'] . "\n";

    write_to_log($logbuf, 'providers.log');

    header("HTTP/1.1 200 OK");
    echo file_get_contents($name);
} else {
    header("HTTP/1.1 404 Not found");
}

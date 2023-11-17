<?php

//Модуль логирования
require_once($_SERVER["DOCUMENT_ROOT"] . "/shared_scripts/logger.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/shared_scripts/Utils.php");

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

<?php
require_once("../shared_scripts/crm_settings.php");
require_once("../shared_scripts/MySQL.php");

$DB = new db_driver();
$DB->obj['sql_database'] = IPTV_DATABASE;
$DB->obj['sql_user'] = IPTV_USER;
$DB->obj['sql_pass'] = IPTV_PASSWORD;

if(!$DB->connect()) {
    write_to_log("proiptv_stat: can't connect", 'error.log');
}

$prev_day = date("m.d.Y");

$res = $DB->query("SELECT model, count(model) AS cnt FROM statistics GROUP BY model ORDER BY model ASC");
if ($res) {
    $stat_all = '';
    while($row = $DB->fetch_row()) {
        $stat_all .= "{$row['cnt']}\t{$row['model']}" . PHP_EOL;
    }
    file_put_contents("stat_all_$prev_day.txt", $stat_all);
}

$res = $DB->query("SELECT model, count(model) AS cnt FROM statistics WHERE date(FROM_UNIXTIME(time))=CURRENT_DATE GROUP BY model ORDER BY cnt DESC");
if ($res) {
    $stat_all = '';
    while($row = $DB->fetch_row()) {
        $stat_all .= "{$row['model']}\t{$row['cnt']}" . PHP_EOL;
    }
    file_put_contents("stat_day_$prev_day.txt", $stat_all);
}

$DB->close_db();

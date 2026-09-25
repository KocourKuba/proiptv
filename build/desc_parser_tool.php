<?php
/**
 * Test tool for the EPG description parser (dune_plugin/lib/desc_parser.php).
 *
 * Reads <programme> nodes from an xml file (a fragment without <tv> root is fine), runs every description
 * through Desc_Parser::reformat() the same way the plugin does and prints to stdout: original and result
 * of each description, the table of extracted data and a summary table.
 *
 * Usage: php build/desc_parser_tool.php <programmes.xml> [--parser=<desc_parser.php>] [--icon=<url>] [--width=<n>]
 *   --parser  parser file to test, default is dune_plugin/lib/desc_parser.php
 *   --icon    icon used for programmes without own <icon>, the plugin uses the channel picon there.
 *             Selects the matcher by picon host, e.g. --icon=https://resizer.mail.ru/x.jpg
 *   --width   width of the value column in tables, default 100
 *
 * Runs on PHP 5.3 (the box runtime) and newer.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

$root = dirname(__DIR__);
$opts = array('parser' => "$root/dune_plugin/lib/desc_parser.php", 'icon' => '', 'width' => 100);
$xml_file = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(parser|icon|width)=(.*)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    } else if ($xml_file === null && $arg[0] !== '-') {
        $xml_file = $arg;
    } else {
        usage("unknown argument: $arg");
    }
}

if ($xml_file === null) {
    usage();
}
if (!is_file($xml_file)) {
    usage("xml file not found: $xml_file");
}
if (!is_file($opts['parser'])) {
    usage("parser file not found: {$opts['parser']}");
}
$width = max(20, (int)$opts['width']);

require "$root/dune_plugin/lib/epg/ext_epg_program.php";
require $opts['parser'];
load_plugin_function("$root/dune_plugin/lib/dune_stb_api.php", 'unescape_entity_string');

$programmes = load_programmes($xml_file);
if (empty($programmes)) {
    fwrite(STDERR, "No <programme> nodes found in $xml_file" . PHP_EOL);
    exit(1);
}

// fields the plugin shows in the extended epg, in the order of the epg dialog
$fields = array(
    PluginTvExtEpgProgram::sub_title,
    PluginTvExtEpgProgram::main_category,
    PluginTvExtEpgProgram::year,
    PluginTvExtEpgProgram::country,
    PluginTvExtEpgProgram::director,
    PluginTvExtEpgProgram::actor,
    PluginTvExtEpgProgram::producer,
    PluginTvExtEpgProgram::writer,
    PluginTvExtEpgProgram::editor,
    PluginTvExtEpgProgram::composer,
    PluginTvExtEpgProgram::presenter,
    PluginTvExtEpgProgram::imdb_rating,
    PluginTvExtEpgProgram::kp_rating,
    PluginTvExtEpgProgram::km_rating,
);

echo 'Parser:     ' . realpath($opts['parser']) . ' (API ' . Desc_Parser::API . ', revision ' . Desc_Parser::REVISION . ')' . PHP_EOL;
echo 'Source:     ' . realpath($xml_file) . PHP_EOL;
echo 'Programmes: ' . count($programmes) . PHP_EOL;

$summary = array();
$total_time = 0;
foreach ($programmes as $n => $p) {
    $icon = empty($p['icon']) ? $opts['icon'] : $p['icon'];

    $start = microtime(true);
    $result = Desc_Parser::reformat($p['title'], $p['desc'], $icon);
    $elapsed = microtime(true) - $start;
    $total_time += $elapsed;

    $matcher = detect_matcher(Desc_Parser::$rules, $p['desc'], $icon);

    echo PHP_EOL . str_repeat('=', $width + 20) . PHP_EOL;
    printf('#%d  %s  %s' . PHP_EOL, $n + 1, $p['time'], $p['channel']);
    echo "Title:   {$p['title']}" . PHP_EOL;
    echo 'Icon:    ' . (empty($icon) ? '-' : $icon) . PHP_EOL;
    echo "Matcher: $matcher" . PHP_EOL;
    printf('Time:    %.1f us' . PHP_EOL, $elapsed * 1e6);

    echo PHP_EOL . '--- original ' . str_repeat('-', 20) . PHP_EOL;
    echo ($p['desc'] === '' ? '(empty)' : $p['desc']) . PHP_EOL;
    echo PHP_EOL . '--- result ' . str_repeat('-', 22) . PHP_EOL;
    $desc = $result[PluginTvExtEpgProgram::desc];
    echo ($desc === '' ? '(empty)' : $desc) . PHP_EOL;

    $rows = array();
    foreach ($fields as $field) {
        if (isset($result[$field])) {
            $rows[] = array($field, $result[$field]);
        }
    }
    if ($result[PluginTvExtEpgProgram::main_icon] !== $icon) {
        $rows[] = array(PluginTvExtEpgProgram::main_icon, $result[PluginTvExtEpgProgram::main_icon]);
    }

    echo PHP_EOL . '--- extracted ' . str_repeat('-', 19) . PHP_EOL;
    if (empty($rows)) {
        echo '(nothing)' . PHP_EOL;
    } else {
        echo render_table(array('field', 'value'), $rows, $width);
    }

    $found = array();
    foreach ($rows as $row) {
        $found[] = $row[0];
    }
    $summary[] = array(
        (string)($n + 1),
        $p['title'],
        $matcher,
        text_length($p['desc']) . ' -> ' . text_length($desc),
        empty($found) ? '-' : implode(', ', $found),
    );
}

echo PHP_EOL . str_repeat('=', $width + 20) . PHP_EOL;
echo 'Summary' . PHP_EOL;
echo render_table(array('#', 'title', 'matcher', 'chars', 'extracted'), $summary, 40);
printf('Total parse time: %.1f us, %.1f us per programme' . PHP_EOL, $total_time * 1e6, $total_time * 1e6 / count($programmes));

///////////////////////////////////////////////////////////////////////////

/**
 * @param string $error
 * @return void
 */
function usage($error = '')
{
    if ($error !== '') {
        fwrite(STDERR, "ERROR: $error" . PHP_EOL);
    }
    fwrite(STDERR, 'Usage: php build/desc_parser_tool.php <programmes.xml> [--parser=<desc_parser.php>] [--icon=<url>] [--width=<n>]' . PHP_EOL);
    exit(1);
}

/**
 * Take a plugin function from its source file without loading the whole plugin runtime
 *
 * @param string $file
 * @param string $name
 * @return void
 */
function load_plugin_function($file, $name)
{
    $src = str_replace("\r\n", "\n", file_get_contents($file));
    if (!preg_match('/^function ' . $name . '\(.*?^}\n/ms', $src, $m)) {
        fwrite(STDERR, "function $name not found in $file" . PHP_EOL);
        exit(1);
    }
    eval($m[0]);
}

/**
 * Read programmes the same way Epg_Manager_Xmltv does: every programme is loaded by DOM wrapped into <tv>,
 * a broken one is reported and skipped; the first non empty title/desc and the first icon are taken
 *
 * @param string $file
 * @return array
 */
function load_programmes($file)
{
    $xml = file_get_contents($file);
    if (!preg_match_all('#<programme[\s>].*?</programme>#s', $xml, $nodes)) {
        return array();
    }

    libxml_use_internal_errors(true);
    $programmes = array();
    foreach ($nodes[0] as $n => $node) {
        $doc = new DOMDocument();
        if ($doc->loadXML("<tv>$node</tv>") === false) {
            foreach (libxml_get_errors() as $error) {
                fwrite(STDERR, sprintf('programme #%d skipped, xml error: %s', $n + 1, trim($error->message)) . PHP_EOL);
            }
            libxml_clear_errors();
            continue;
        }
        $programmes[] = parse_programme($doc->getElementsByTagName('programme')->item(0));
    }

    return $programmes;
}

/**
 * @param DOMElement $tag
 * @return array
 */
function parse_programme($tag)
{
    // shown as written in the file, xmltv time is 'YYYYmmddHHMMSS +zzzz'
    $time = format_xmltv_time($tag->getAttribute('start'));
    $stop = format_xmltv_time($tag->getAttribute('stop'));
    if ($stop !== '') {
        // same day: only the clock of the stop time
        $time .= ' - ' . (strncmp($time, $stop, 11) === 0 ? substr($stop, 11) : $stop);
    }

    $icon = '';
    foreach ($tag->getElementsByTagName('icon') as $element) {
        $icon = $element->getAttribute('src');
        break;
    }

    return array(
        'time' => $time,
        'channel' => $tag->getAttribute('channel'),
        'title' => node_value($tag, 'title'),
        'desc' => unescape_entity_string(node_value($tag, 'desc')),
        'icon' => $icon,
    );
}

/**
 * @param string $value
 * @return string
 */
function format_xmltv_time($value)
{
    if (!preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})\d*\s*(.*)$/', trim($value), $m)) {
        return trim($value);
    }
    return rtrim("$m[1]-$m[2]-$m[3] $m[4]:$m[5] $m[6]");
}

/**
 * @param DOMElement $node
 * @param string $name
 * @return string
 */
function node_value($node, $name)
{
    foreach ($node->getElementsByTagName($name) as $element) {
        if (!empty($element->nodeValue)) {
            return $element->nodeValue;
        }
    }
    return '';
}

/**
 * Matcher chosen by Desc_Parser::reformat(), repeats its selection so the tool can show it
 *
 * @param array $rules
 * @param string $raw_descr
 * @param string $icon
 * @return string
 */
function detect_matcher($rules, $raw_descr, $icon)
{
    if (empty($raw_descr) || !isset($rules['matchers'])) {
        return 'default';
    }

    $raw_descr = str_replace("\r\n", "\n", $raw_descr);
    foreach ($rules['matchers'] as $name => $cfg) {
        if (isset($cfg['icon'])) {
            foreach ((array)$cfg['icon'] as $needle) {
                if (strpos($icon, $needle) !== false) {
                    return "$name (icon)";
                }
            }
        }
        if (isset($cfg['detect']) && preg_match($cfg['detect'], $raw_descr)) {
            return "$name (detect)";
        }
    }

    return 'default';
}

/**
 * @param string $text
 * @return int
 */
function text_length($text)
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : preg_match_all('/./us', $text, $m);
}

/**
 * Split text to lines not longer than $width characters, words are not broken unless longer than a line
 *
 * @param string $text
 * @param int $width
 * @return array
 */
function wrap_text($text, $width)
{
    $lines = array();
    foreach (explode("\n", str_replace(array("\r\n", "\t"), array("\n", ' '), $text)) as $paragraph) {
        $line = '';
        foreach (explode(' ', $paragraph) as $word) {
            while (text_length($word) > $width) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                preg_match('/^.{' . $width . '}/us', $word, $m);
                $lines[] = $m[0];
                $word = substr($word, strlen($m[0]));
            }
            if ($line === '') {
                $line = $word;
            } else if (text_length($line) + 1 + text_length($word) <= $width) {
                $line .= " $word";
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }
        $lines[] = $line;
    }
    return $lines;
}

/**
 * @param string $text
 * @param int $width
 * @return string
 */
function pad($text, $width)
{
    return $text . str_repeat(' ', max(0, $width - text_length($text)));
}

/**
 * Text table, cells are wrapped to $max_width characters
 *
 * @param array $header
 * @param array $rows
 * @param int $max_width
 * @return string
 */
function render_table($header, $rows, $max_width)
{
    $widths = array();
    foreach ($header as $i => $title) {
        $widths[$i] = text_length($title);
    }

    $wrapped = array();
    foreach ($rows as $row) {
        $cells = array();
        foreach ($row as $i => $cell) {
            $cells[$i] = wrap_text((string)$cell, $max_width);
            foreach ($cells[$i] as $line) {
                $widths[$i] = max($widths[$i], text_length($line));
            }
        }
        $wrapped[] = $cells;
    }

    $separator = '+';
    foreach ($widths as $w) {
        $separator .= str_repeat('-', $w + 2) . '+';
    }
    $separator .= PHP_EOL;

    $out = $separator;
    $line = '|';
    foreach ($header as $i => $title) {
        $line .= ' ' . pad($title, $widths[$i]) . ' |';
    }
    $out .= $line . PHP_EOL . $separator;

    foreach ($wrapped as $cells) {
        $height = 0;
        foreach ($cells as $cell) {
            $height = max($height, count($cell));
        }
        for ($l = 0; $l < $height; $l++) {
            $line = '|';
            foreach ($widths as $i => $w) {
                $line .= ' ' . pad(isset($cells[$i][$l]) ? $cells[$i][$l] : '', $w) . ' |';
            }
            $out .= $line . PHP_EOL;
        }
    }

    return $out . $separator;
}

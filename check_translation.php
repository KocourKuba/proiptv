<?php /** @noinspection ALL */

/**
 * Search recursively for files in a base directory matching a glob pattern.
 * The `GLOB_NOCHECK` flag has no effect.
 *
 * @param  string $base Directory to search
 * @param  string $pattern Glob pattern to match files
 * @param  int $flags Glob flags from https://www.php.net/manual/function.glob.php
 * @return string[] Array of files matching the pattern
 */
function glob_recursive($base, $pattern, $flags = 0) {
    $flags &= ~GLOB_NOCHECK;

    if (substr($base, -1) !== '/') {
        $base .= '/';
    }

    $files = glob($base.$pattern, $flags);
    if (!is_array($files)) {
        $files = array();
    }

    $dirs = glob($base.'*', GLOB_ONLYDIR|GLOB_NOSORT|GLOB_MARK);
    if (!is_array($dirs)) {
        return $files;
    }

    foreach ($dirs as $dir) {
        $dirFiles = glob_recursive($dir, $pattern, $flags);
        foreach($dirFiles as $file) {
            $files[] = $file;
        }
    }

    return $files;
}

$plugin_path = "dune_plugin";
$translation_path = "$plugin_path/translations";
$translation_prefix = 'dune_language_';

// keys that are only used through a variable (TR::t($key)) and can't be found by scanning the sources
$dynamic_keys = array(
    'plugin_vod', // VOD_GROUP_CAPTION
    'entry_force_open', // build/dune_plugin.xml.tpl
    'entry_reboot', // build/dune_plugin.xml.tpl
    'vod_filter_type_animation', // vod_ttv: "vod_filter_type_$type"
    'vod_filter_type_film',
    'vod_filter_type_serial',
);

// load translations
$translations = array();
$usage = array();
$translation_files = glob("$translation_path/$translation_prefix*.txt");
if (!is_array($translation_files)) {
    $translation_files = array();
}

foreach ($translation_files as $file) {
    $lang = substr(basename($file, '.txt'), strlen($translation_prefix));

    $lines = file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        echo "Can't parse $file" . PHP_EOL;
        continue;
    }

    $translations[$lang] = array();
    $usage[$lang] = array();
    $line_num = 0;
    foreach($lines as $line) {
        ++$line_num;
        if ($line_num === 1 && strncmp($line, "\xEF\xBB\xBF", 3) === 0) {
            $line = substr($line, 3);
        }

        $pair = explode('=', $line, 2);
        if (count($pair) !== 2) {
            echo "$lang: malformed line $line_num: '$line'" . PHP_EOL;
            continue;
        }

        $key = trim($pair[0]);
        if ($key === '') continue;

        if (isset($translations[$lang][$key])) {
            echo "$lang: duplicate translation '$key' at line $line_num" . PHP_EOL;
        }
        $translations[$lang][$key] = $pair[1];
        $usage[$lang][$key] = 0;
    }
    echo "Loaded " . count($translations[$lang]) . " translations for lang: $lang" . PHP_EOL;
}

if (empty($translations)) {
    echo "No translation files found!" . PHP_EOL;
    die();
}

// every language must contain every key that exists in any language
$all_keys = array();
foreach($translations as $t) {
    $all_keys += $t;
}

foreach($translations as $lang => $t) {
    foreach(array_diff_key($all_keys, $t) as $key => $value) {
        echo "missed translation '$key' in $lang" . PHP_EOL;
    }
}

foreach ($dynamic_keys as $key) {
    foreach ($usage as $lang => $keys) {
        if (isset($keys[$key])) {
            ++$usage[$lang][$key];
        }
    }
}

// TR::t/g/load( first argument ) - nested calls in the argument may contain one level of parentheses
$regex_call = '/TR::(?:g|t|load)\(\s*([^,()]*(?:\([^()]*\)[^,()]*)*)/';
// quoted key inside the argument, a literal joined with '.' is a part of a dynamic key
$regex_key = '/(\.\s*)?([\'"])([_a-zA-Z0-9]+)\2(\s*\.)?/';
// %tr%key in strings and xml
$regex_tr = '/[\'">]%tr%([_a-zA-Z0-9]+)[\'"<]/';

$files_to_check = array_merge(glob_recursive($plugin_path, "*.php"), glob_recursive($plugin_path, "*.xml"));
foreach ($files_to_check as $src_file) {
    $content = file_get_contents($src_file);
    if ($content === false) {
        echo "Can't read $src_file" . PHP_EOL;
        continue;
    }

    // key => list of offsets
    $found = array();
    if (preg_match_all($regex_call, $content, $calls, PREG_SET_ORDER|PREG_OFFSET_CAPTURE)) {
        foreach ($calls as $call) {
            // drop nested calls, their string arguments are not translation keys
            $arg = preg_replace('/\([^()]*\)/', '()', $call[1][0]);
            if (!preg_match_all($regex_key, $arg, $keys, PREG_SET_ORDER)) continue;

            foreach ($keys as $key) {
                if (!empty($key[1]) || !empty($key[4])) continue;
                $found[$key[3]][] = $call[1][1];
            }
        }
    }

    if (preg_match_all($regex_tr, $content, $trs, PREG_SET_ORDER|PREG_OFFSET_CAPTURE)) {
        foreach ($trs as $tr) {
            $found[$tr[1][0]][] = $tr[1][1];
        }
    }

    foreach ($found as $key => $offsets) {
        foreach ($translations as $lang => $t) {
            if (isset($t[$key])) {
                $usage[$lang][$key] += count($offsets);
                continue;
            }

            foreach ($offsets as $offset) {
                $line_num = substr_count($content, "\n", 0, $offset) + 1;
                echo "$lang: Unknown translation '$key' in file: $src_file, line: $line_num" . PHP_EOL;
            }
        }
    }
}

foreach ($usage as $lang => $keys) {
    foreach ($keys as $key => $count) {
        if ($count === 0) {
            echo "Unused translation '$key' in lang: $lang" . PHP_EOL;
        }
    }
}

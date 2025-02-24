<?php /** @noinspection ALL */

/**
 * Search recusively for files in a base directory matching a glob pattern.
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

// load translations
$max_cnt = 0;
$max = '';
foreach (glob("$translation_path/*.txt") as $file) {
    $ar = explode('/', $file);
    $ar = (explode('_',(count($ar) === 1) ? $file : end($ar)));
    $lang = end($ar);

    $lines = file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        echo "Can't parse $file" . PHP_EOL;
        continue;
    }

    $translations[$lang] = array();
    $usage[$lang] = array();
    foreach($lines as $line) {
        list($key, $value) = explode("=", $line);
        $key = trim($key);
        if (empty($key)) continue;

        if (isset($translations[$lang][$key])) {
            echo "duplicate translation $key" . PHP_EOL;
        }
        $translations[$lang][$key] = $value;
        $usage[$lang][$key] = 0;
    }
    $cnt = count($translations[$lang]);
    if ($max_cnt < $cnt) {
        $max = $lang;
        $max_cnt = $cnt;
    }
    echo "Loaded $cnt translations for lang: $lang" . PHP_EOL;
}

if (empty($translations)) {
    echo "No translation files found!" . PHP_EOL;
    die();
}

foreach($translations as $k => $t) {
    if ($k === $max || count($t) === $max_cnt) continue;

    $diff = array_diff_key($translations[$max], $t);
    foreach($diff as $kt => $vt) {
        echo "missed translation '$kt' in $k" . PHP_EOL;
    }
}

$regex_t = "/.*TR::(?:g|t|load)\(['\"]([_a-z0-9]+)['\"].*|.*['\">]%tr%([_a-z0-9]+)['\"<].*/U";
$files_to_check = glob_recursive($plugin_path, "*.php");
$files_to_check = array_merge($files_to_check, glob_recursive($plugin_path, "*.xml"));
foreach ($files_to_check as $php_file) {
    $lines = file($php_file, FILE_IGNORE_NEW_LINES);
    $line_num = 1;
    foreach ($lines as $line) {
        $offset = 0;
        while(preg_match($regex_t, $line, $m, PREG_OFFSET_CAPTURE, $offset)) {
            if (isset($m[2])) {
                $tr = $m[2][0];
                $offset = $m[2][1] + strlen($tr);
            } else {
                $tr = $m[1][0];
                $offset = $m[1][1] + strlen($tr);
            }
            foreach ($translations as $lang_name => $lang) {
                if (isset($lang[$tr])) {
                    $usage[$lang_name][$tr]++;
                } else {
                    echo "$lang_name: Unknown translation '$tr' for  in file: $php_file, line: $line_num" . PHP_EOL;
                }
            }
        }
        ++$line_num;
    }
}

foreach ($usage as $lang => $key) {
    foreach ($key as $k => $v) {
        if ($v === 0) {
            echo "Unused translation '$k' in file: $lang" . PHP_EOL;
        }
    }
}

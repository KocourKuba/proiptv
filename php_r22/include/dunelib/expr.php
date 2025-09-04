<?php

require_once 'dunelib/config_utils.php';

class Expr
{
    // All strings below are kept lower-case.

    private $strict_country;
    private $strict_code_2;
    private $country;
    private $country_code_2;

    private $ilang;
    private $lang_code;

    private $firmware_features;
    private $firmware_version;
    private $product;
    private $platform_kind;
    private $platform_kind_group;
    private $android_platform;
    private $apk;
    private $fw_apk;
    private $limited_apk;

    private $user_ops = array();
    private $user_data = null;

    private function __construct()
    { }

    public static function make()
    {
        $cfg = ConfigUtils::get_config();
        $loc = ConfigUtils::get_location_info();

        $expr = new Expr();

        $expr->ilang = $cfg->interface_language;
        $expr->lang_code = $cfg->lang_code;

        $expr->strict_country = $loc->country ?
            mb_strtolower($loc->country, "UTF-8") : 'ww';
        $expr->strict_code_2 = $loc->country_code_2 ?
            strtolower($loc->country_code_2) : 'ww';

        $wc = mb_strtolower($cfg->widget_country, "UTF-8");
        $expr->country = $wc ? $wc : $expr->strict_country;

        if ($wc && strlen($wc) == 2 &&
            ctype_alpha($wc[0]) && ctype_alpha($wc[1]))
        {
            $expr->country_code_2 = $wc;
        }
        else
            $expr->country_code_2 = strtolower($loc->country_code_2);

        $expr->firmware_features = self::get_firmware_features();

        $pfw = self::get_product_and_fw();
        $expr->firmware_version = $pfw->firmware_version;
        $expr->product = $pfw->product;
        $expr->platform_kind = $pfw->platform_kind;
        $expr->platform_kind_group = $pfw->platform_kind_group;
        $expr->android_platform = $pfw->android_platform;

        $expr->apk = getenv("HD_APK") == "1" ? 1 : 0; 
        $expr->fw_apk = getenv("HD_FW_APK") == "1" ? 1 : 0; 
        $expr->limited_apk = $expr->apk && !$expr->fw_apk ? 1 : 0;
        return $expr;
    }

    private static function get_firmware_features()
    {
        static $ffs = null;
        if (!$ffs)
            $ffs = ConfigUtils::load_firmware_features();
        return $ffs;
    }

    private static function get_product_and_fw()
    {
        static $pfw = null;
        if (!$pfw)
            $pfw = ConfigUtils::load_product_and_fw();
        return $pfw;
    }

    public function add_user_op($op_name, $func_name)
    {
        $this->user_ops[$op_name] = (object) array(
            'op_name' => $op_name,
            'func_name' => $func_name);
    }

    public function set_user_data($user_data)
    {
        $this->user_data = $user_data;
    }

    private static function skip_spaces($pat, &$n)
    {
        $pat_len = strlen($pat);
        while ($n < $pat_len && ctype_space($pat[$n]))
            $n++;
    }

    private static function open_to_number($c) {
        switch ($c) {
            case '(': return 1;
            case '{': return 2;
            case '<': return 3;
        }
        return 0;
    }

    private static function close_to_number($c) {
        switch ($c) {
            case ')': return 1;
            case '}': return 2;
            case '>': return 3;
        }
        return 0;
    }

    private static function number_to_open($pars_num) {
        $arr = array('', '(', '{', '<');
        return $pars_num < count($arr) ? $arr[$pars_num] : '';
    }

    private static function number_to_close($pars_num) {
        $arr = array('', ')', '}', '>');
        return $pars_num < count($arr) ? $arr[$pars_num] : '';
    }

    private static function parse_op($pat)
    {
        $pat_len = strlen($pat);

        $n = 0;
        self::skip_spaces($pat, $n);

        if ($n == $pat_len)
        {
            echo("Pattern error: expected op missing (pat $pat)\n");
            return array(null, $n);
        }

        $op = null;
        if ($pat[$n] == '!')
        {
            $op = '!';
            $n++;
        }
        else
        {
            $start_n = $n;
            while ($n < $pat_len &&
                (ctype_alpha($pat[$n]) || $pat[$n] == '_'))
            {
                $n++;
            }

            if ($n == $start_n)
            {
                echo("Pattern error: got invalid op char (pat $pat)\n");
                return array(null, $n);
            }
            $op = strtolower(substr($pat, $start_n, $n - $start_n));
        }

        self::skip_spaces($pat, $n);

        while ($n < $pat_len)
        {
            $num1 = self::open_to_number($pat[$n]);
            $num2 = self::close_to_number($pat[$pat_len - 1]);

            if ($num1 > 0 && $num2 != $num1)
            {
                $close = self::number_to_close($num1);
                echo("Pattern error: invalid op, close '$close' missing (pat $pat)\n");
            }
            else
                break;

            return array(null, $n);
        }

        return array($op, $n);
    }

    private static function match_to_list($value, $pat)
    {
#echo("match_to_list(val $value, pat $pat)\n");
        if ($pat === 'any')
            return true;

        $value = trim($value);
        if ($value === '')
            return false;

        $len = strlen($pat);
        if ($len == 0)
        {
            echo("Warning: empty pattern\n");
            return false;
        }

        $items = explode(",", $pat);
        foreach ($items as $item)
        {
            if (self::match_single($value, trim($item)))
                return true;
        }
        return false;
    }

    private static function match_single($value, $pat)
    {
        $regex_type = 0;

        # NOTE: now search is always case insensitive => change when needed
        $regex_mod = 'i';

        $len = strlen($pat);

        if (!strncmp("=~", $pat, 2) || !strncmp("!~", $pat, 2))
        {
            $regex_type = $pat[0] == '=' ? 1 : 2;
            $pat = substr($pat, 2);
        }
        else if (!strncmp("i=~", $pat, 3) || !strncmp("i!~", $pat, 3))
        {
            $regex_type = $pat[1] == '=' ? 1 : 2;
            $regex_mod = 'i';
            $pat = substr($pat, 3);
        }

        if ($regex_type)
        {
            $pat = trim($pat);
            if ($pat != '')
            {
                $res = preg_match("#$pat#$regex_mod", $value);
                return $regex_type == 1 ? $res : !$res;
            }
        }

        return 0 == strcasecmp($pat, $value);
    }

    private static function get_block($pat, $pars_num, &$n, &$pat2)
    {
        $open_c = self::number_to_open($pars_num);
        $close_c = self::number_to_close($pars_num);

        $depth = 0;
        $start = $n;
        $len = strlen($pat);
        for (;; $n++)
        {
            if ($n == $len)
            {
                if ($pars_num)
                {
                    echo("Pattern error: unmatched $open_c\n");
                    break;
                }

                $pat2 = substr($pat, $start, $n - $start);
                return true;
            }

            if ($pat[$n] == $open_c ||
                ($pars_num == 0 && self::open_to_number($pat[$n])))
            {
                $depth++;
            }
            else if ($pat[$n] == $close_c ||
                ($pars_num == 0 && self::open_to_number($pat[$n])))
            {
                $depth--;
                if ($depth < 0)
                {
                    if ($n == $start)
                        return false;
                    $pat2 = substr($pat, $start, $n - $start);
                    return true;
                }
            }
            else if ($pat[$n] == ',' && $pars_num)
            {
                if ($depth == 0)
                {
                    $pat2 = substr($pat, $start, $n - $start);
                    $n++;
                    return true;
                }
            }
        }
        return false;
    }

    private function do_evaluate($pat)
    {
        $pat = trim($pat);

        if ($pat == 'any')
            return true;
        if ($pat == '')
            return false;

        list($op, $n) = self::parse_op($pat);
        if (!isset($op))
            return false;

        $pat_len = strlen($pat);
        $with_args = $n < $pat_len;

        $pars_num = $with_args ? self::open_to_number($pat[$n]) : 0;

        $len = $pat_len - $n;
        if ($pars_num) {
            $n = $n + 1;
            $len = $pat_len - $n - 1;
        }

        $pat2 = trim(substr($pat, $n, $len));

        switch ($op)
        {
            case 'or':
                while (self::get_block($pat, $pars_num, $n, $pat2))
                {
#echo("or block($pat2)\n");
                    if ($this->do_evaluate($pat2))
                        return true;
                }
                return false;

            case 'and':
                while (self::get_block($pat, $pars_num, $n, $pat2))
                {
#echo("and block($pat2)\n");
                    if (!$this->do_evaluate($pat2))
                        return false;
                }
                return true;

            case 'not':
            case '!':
                if (self::get_block($pat, $pars_num, $n, $pat2))
                {
#echo("not block($pat2)\n");
                    return !$this->do_evaluate($pat2);
                }
                return true;

            case 'strict_country':
                return self::match_to_list($this->strict_country, $pat2);
            case 'country':
            case 'c':
                return self::match_to_list($this->country_code_2, $pat2) ||
                     self::match_to_list($this->country, $pat2);
            case 'language':
            case 'l':
            {
                return self::match_to_list($this->lang_code, $pat2) ||
                    self::match_to_list($this->ilang, $pat2);
            }
            case 'product':
            case 'p':
                return
                    self::match_to_list($this->product, $pat2) ||
                    self::match_to_list($this->platform_kind, $pat2) ||
                    self::match_to_list($this->platform_kind_group, $pat2);
            case 'android_platform':
                return self::match_to_list($this->android_platform, $pat2);
            case 'firmware_feature':
            case 'ff':
                return isset($this->firmware_features[$pat2]);
            case 'firmware_version':
            case 'fwv':
                return self::match_to_list(
                    $this->firmware_version, substr($pat, $n, $len));

            case 'firmware_version_ge':
            case 'fwv_ge':
                return strcmp($this->firmware_version, $pat2) >= 0;
            case 'firmware_version_gt':
            case 'fwv_gt':
                return strcmp($this->firmware_version, $pat2) > 0;
            case 'firmware_version_le':
            case 'fwv_le':
                return strcmp($this->firmware_version, $pat2) <= 0;
            case 'firmware_version_lt':
            case 'fwv_lt':
                return strcmp($this->firmware_version, $pat2) < 0;

            case 'apk':
                return $this->apk;
            case 'fw_apk':
                return $this->fw_apk;
            case 'limited_apk':
                return $this->limited_apk;
        }

        if (!isset($this->user_ops[$op]))
            return false;

        $user_op = $this->user_ops[$op];
        return call_user_func($user_op->func_name,
            $op, $pat2, $this->user_data);
    }

    public function evaluate($pat)
    {
        $pat = strtolower($pat);
        return $this->do_evaluate($pat);
    }

   /////////////////////////////////////////////////////////////////////// 

    // Added for autotests only.
    public static function test_create($arr)
    {
        $expr = new Expr();
        foreach ($arr as $k => $v)
            $expr->$k = $v;
        $expr->limited_apk = $expr->apk && !$expr->fw_apk ? 1 : 0;
        return $expr;
    }
}

?>

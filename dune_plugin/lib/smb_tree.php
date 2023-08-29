<?php

require_once 'hd.php';

/**
 * @author Andrii Kopyniak
 * @date   2013 Jan 10
 */
class smb_tree
{
    private $descriptor_spec;
    private $env = array('LD_LIBRARY_PATH' => '/tango/firmware/lib');
    private $smb_tree_output = '';
    private $return_value = 0;
    private $no_pass = true;
    private $debug_level = 0;

    public function __construct()
    {
        $this->descriptor_spec = array
        (
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );
    }

    private function get_auth_options()
    {
        return ($this->is_no_pass()) ? '-N' : '';
    }

    private function get_debug_level()
    {
        return '--debuglevel ' . $this->debug_level;
    }

    private function is_no_pass()
    {
        return $this->no_pass;
    }

    /*
     * @return 0 if success
     */
    private function execute($args = '')
    {
        $cmd = '/tango/firmware/bin/smbtree ' . $this->get_auth_options() . ' ' . $this->get_debug_level() . ' ' . $args;
        //hd_print("smbtree exec: $cmd");
        $process = proc_open($cmd, $this->descriptor_spec, $pipes, '/tmp', $this->env);

        if (is_resource($process)) {
            $this->smb_tree_output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            // Важно закрывать все каналы перед вызовом
            // proc_close во избежание мертвой блокировки
            $this->return_value = proc_close($process);
        }

        return $this->return_value;
    }

    private static function parse_smbtree_output($input_lines)
    {
        $output = array();

        if (empty($input_lines)) {
            return array();
        }

        $output_lines = explode("\n", $input_lines);
        if ($output_lines === false) {
            return array();
        }

        foreach ($output_lines as $line) {
            if (!empty($line)) {
                $detail_info = explode("\t", $line);

                if (count($detail_info)) {
                    $q = isset($detail_info[1]) ? $detail_info[1] : '';
                    $output[$detail_info[0]] = array
                    (
                        'name' => $detail_info[0],
                        'comment' => $q,
                    );
                }
            }
        }

        return $output;
    }

    public function get_xdomains()
    {
        return ($this->execute('--xdomains') !== 0) ? array() : self::parse_smbtree_output($this->smb_tree_output);
    }

    public function get_domains()
    {
        return ($this->execute('--domains') !== 0) ? array() : self::parse_smbtree_output($this->smb_tree_output);
    }

    public function get_workgroup_servers($domain)
    {
        return ($this->execute('--workgroup-servers=' . $domain) !== 0) ? array() : self::parse_smbtree_output($this->smb_tree_output);
    }

    public function get_server_shares($server)
    {
        return ($this->execute('--server-shares=' . $server) !== 0) ? array() : self::parse_smbtree_output($this->smb_tree_output);
    }

    public static function get_df_smb()
    {
        $df_smb = array();
        $out_mount = file_get_contents("/proc/mounts");
        if (preg_match_all('|(.+)/tmp/mnt/smb/(.+?) |', $out_mount, $match)) {
            foreach ($match[2] as $k => $v) {
                $df_smb[str_replace(array('/', '\134'), '', $match[1][$k])] = $v;
            }
        }
        return $df_smb;
    }

    public static function get_nmblookup_path()
    {
        return is_apk()
            ? 'export LD_LIBRARY_PATH=$FS_PREFIX/lib:$LD_LIBRARY_PATH&&$FS_PREFIX/bin/nmblookup --configfile=$FS_PREFIX/etc/samba/smb.conf '
            : 'export LD_LIBRARY_PATH=/firmware/lib:$LD_LIBRARY_PATH&&/firmware/bin/nmblookup';
    }

    public static function get_network_folder_smb()
    {
        $d = array();
        $network = parse_ini_file('/config/network_folders.properties', true);
        $network_folder = array();
        foreach ($network as $k => $v) {
            if (preg_match("/(.*)\.(.*)/", $k, $match)) {
                $network_folder[$match[2]][$match[1]] = $v;
            }
        }

        if (count($network_folder) > 0) {
            foreach ($network_folder as $v) {
                if ((int)$v['type'] === 0) {
                    $dd['foldername'] = $v['name'];
                    if (!empty($v['user'])) {
                        $dd['user'] = $v['user'];
                    }
                    if (!empty($v['password'])) {
                        $dd['password'] = $v['password'];
                    }
                    $d[$v['server']][$v['directory']] = $dd;
                }
            }
        }
        return $d;
    }

    public function get_server_shares_smb()
    {
        $d = array();
        $data = $this->get_xdomains();
        foreach ($data as $domain) {
            $data = $this->get_workgroup_servers($domain['name']);
            foreach ($data as $shares) {
                $d[$shares['name']] = $this->get_server_shares($shares['name']);
            }
        }
        return $d;
    }

    public static function get_ip_network_folder_smb()
    {
        $d = array();
        $network_folder_smb = self::get_network_folder_smb();
        foreach ($network_folder_smb as $k => $v) {
            if (!preg_match('/((25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(25[0-5]|2[0-4]\d|[01]?\d\d?)/', $k)) {
                $out = shell_exec(self::get_nmblookup_path() . ' "' . $k . '" -S');
                if (preg_match('/(.*) (.*)<00>/', $out, $matches)) {
                    $ip = '//' . $matches[1] . '/';
                    if ($matches[2] === (string)$k) {
                        foreach ($v as $key => $vel) {
                            $d[$ip . $key] = $vel;
                        }
                    }
                }
            } else {
                foreach ($v as $key => $vel) {
                    $d['//' . $k . '/' . $key] = $vel;
                }
            }
        }
        return $d;
    }

    public function get_ip_server_shares_smb()
    {
        $d = array();
        $my_ip = get_ip_address();
        $server_shares_smb = $this->get_server_shares_smb();
        foreach ($server_shares_smb as $k => $v) {
            //hd_print("server shares: $k");
            $out = shell_exec(self::get_nmblookup_path() . ' "' . $k . '" -R');
            if (preg_match('/(.*) (.*)<00>/', $out, $matches)) {
                if ($my_ip === $matches[1]) {
                    continue;
                }

                $ip = '//' . $matches[1] . '/';
                if ($matches[2] === (string)$k) {
                    foreach ($v as $key => $vel) {
                        $vel['foldername'] = $key . ' in ' . $k;
                        $d[$ip . $key] = $vel;
                    }
                }
            }
        }
        return $d;
    }

    public static function write_request($server_id, $cmd_name, $cmd_id, $params)
    {
        $dir_path = "/tmp/run/ipc__$server_id";
        if (!is_dir($dir_path)) {
            return false;
        }

        $pid = posix_getpid();
        $path = "$dir_path/$cmd_name.$pid-$cmd_id.cmd";
        $tmp_path = "$path.tmp";

        $fp = fopen($tmp_path, 'wb');
        if ($fp === false) {
            return false;
        }

        foreach ($params as $key => $value) {
            fprintf($fp, "$key = $value\n");
        }

        fclose($fp);

        if (false === rename($tmp_path, $path)) {
            unlink($tmp_path);
            return false;
        }

        $path = "$dir_path/$cmd_name.$pid-$cmd_id.res";
        sleep(1);
        if (!file_exists($path)) {
            return false;
        }

        $res = parse_ini_file($path, true);
        unlink($path);
        $path = '/tmp/run/network_mount_list.xml';
        if (!file_exists($path)) {
            return false;
        }

        $xml = simplexml_load_string(file_get_contents($path));
        if ($xml === false) {
            hd_print(__METHOD__ . ": Error parsing $path.");
            return false;
        }

        foreach ($xml->children() as $elt) {
            $elt_name = $elt->getName();
            if ($elt_name !== 'mount') {
                continue;
            }

            $id = (int)$elt["id"];
            $path = (string)$elt["path"];
            $type = (string)$elt["type"];
            if (($id === (int)$res['id']) && ($type === $params['type'])) {
                return $path;
            }
        }

        return false;
    }

    public static function get_mount_smb($ip_smb)
    {
        $mounts = array();
        foreach ($ip_smb as $k => $vel) {
            $df_smb = self::get_df_smb();
            if (isset($df_smb[str_replace(array('/', '\134'), '', $k)])) {
                $mounts['/tmp/mnt/smb/' . $df_smb[str_replace(array('/', '\134'), '', $k)]]['foldername'] = $vel['foldername'];
                $mounts['/tmp/mnt/smb/' . $df_smb[str_replace(array('/', '\134'), '', $k)]]['ip'] = $k;
                if (!empty($vel['user'])) {
                    $mounts['/tmp/mnt/smb/' . $df_smb[str_replace(array('/', '\134'), '', $k)]]['user'] = $vel['user'];
                }

                if (!empty($vel['password'])) {
                    $mounts['/tmp/mnt/smb/' . $df_smb[str_replace(array('/', '\134'), '', $k)]]['password'] = $vel['password'];
                }
            } else {
                $ret_code = false;
                $n = count($df_smb);
                $username = 'guest';
                $password = '';
                if (!empty($vel['user'])) {
                    $username = $vel['user'];
                }

                if (!empty($vel['password'])) {
                    $password = $vel['password'];
                }

                $path_parts = pathinfo($k);
                $wr = self::write_request('network_manager', 'mount', $n,
                    array(
                        'type' => 'smb',
                        'server' => str_replace("//", "", $path_parts['dirname']),
                        'dir' => $path_parts['basename'],
                        'user_name' => $username,
                        'password' => $password,
                        'proto' => '',
                    ));

                if ($wr === false) {
                    $fn = '/tmp/mnt/smb/' . $n;
                    if (!file_exists($fn) && !mkdir($fn, 0777, true) && !is_dir($fn)) {
                        hd_print(__METHOD__ . ": Directory '$fn' was not created");
                    }
                    $ret_code = exec("mount -t cifs -o username=$username,password=$password,posixpaths,rsize=32768,wsize=130048 \"$k\" \"$fn\" 2>&1 &");
                } else {
                    $fn = $wr;
                }

                if ($ret_code !== false) {
                    $mounts['err_' . $vel['foldername']]['foldername'] = $vel['foldername'];
                    $mounts['err_' . $vel['foldername']]['ip'] = $k;
                    $mounts['err_' . $vel['foldername']]['err'] = trim($ret_code);

                    if (!empty($vel['user'])) {
                        $mounts['err_' . $vel['foldername']]['user'] = $vel['user'];
                    }

                    if (!empty($vel['password'])) {
                        $mounts['err_' . $vel['foldername']]['password'] = $vel['password'];
                    }
                } else {
                    $mounts[$fn]['foldername'] = $vel['foldername'];
                    $mounts[$fn]['ip'] = $k;
                    if (!empty($vel['user'])) {
                        $mounts[$fn]['user'] = $vel['user'];
                    }
                    if (!empty($vel['password'])) {
                        $mounts[$fn]['password'] = $vel['password'];
                    }
                }
            }
        }
        return $mounts;
    }

    public function get_mount_all_smb($info)
    {
        if ($info !== 3) { // not SMB search
            $folders = self::get_mount_smb(self::get_ip_network_folder_smb());

            if ($info === 1) { // only network folders
                return $folders;
            }
        }

        $shares = self::get_mount_smb($this->get_ip_server_shares_smb());
        if ($info === 3) { // only SMB search
            return $shares;
        }

        // network folders and network folders + SMB search
        return array_merge($shares, $folders);
    }

    public static function get_network_folder_nfs()
    {
        $nfs = array();
        $network = parse_ini_file('/config/network_folders.properties', true);
        foreach ($network as $k => $v) {
            if (preg_match("/(.*)\.(.*)/", $k, $match)) {
                $network_folder[$match[2]][$match[1]] = $v;
            }
        }

        if (isset($network_folder)) {
            foreach ($network_folder as $v) {
                if ((int)$v['type'] === 1) {
                    $p = ((int)$v['protocol'] === 1) ? 'tcp' : 'udp';
                    $nfs[$v['server'] . ':' . $v['directory']]['foldername'] = $v['name'];
                    $nfs[$v['server'] . ':' . $v['directory']]['protocol'] = $p;
                    $nfs[$v['server'] . ':' . $v['directory']]['server'] = $v['server'];
                    $nfs[$v['server'] . ':' . $v['directory']]['directory'] = $v['directory'];
                }
            }
        }
        return $nfs;
    }

    public static function get_df_nfs()
    {
        $df_nfs = array();
        $out_mount = file_get_contents("/proc/mounts");
        if (preg_match_all('|(.+) /tmp/mnt/network/(.+?) |', $out_mount, $match)) {
            foreach ($match[2] as $k => $v) {
                $df_nfs[$match[1][$k]] = $v;
            }
        }
        return $df_nfs;
    }

    public static function get_mount_nfs()
    {
        $d = array();
        $ip_nfs = self::get_network_folder_nfs();
        $df_nfs = self::get_df_nfs();
        foreach ($ip_nfs as $k => $vel) {
            if (isset($df_nfs[$k])) {
                $d['/tmp/mnt/network/' . $df_nfs[$k]]['foldername'] = $vel['foldername'];
                $d['/tmp/mnt/network/' . $df_nfs[$k]]['ip'] = $k;
                $d['/tmp/mnt/network/' . $df_nfs[$k]]['protocol'] = $vel['protocol'];
            } else {
                $q = false;
                $n = count($df_nfs) + 100;
                $wr = self::write_request('network_manager', 'mount', $n,
                    array(
                        'type' => 'nfs',
                        'server' => $vel['server'],
                        'dir' => $vel['directory'],
                        'proto' => $vel['protocol'],
                    ));

                if ($wr === false) {
                    $fn = '/tmp/mnt/network/' . $n;
                    if (!file_exists($fn) && !mkdir($fn, 0777, true) && !is_dir($fn)) {
                        hd_print(__METHOD__ . ": Directory '$fn' was not created");
                    }
                    $q = shell_exec("mount -t nfs -o " . $vel['protocol'] . " $k $fn 2>&1");
                } else {
                    $fn = $wr;
                }

                if ($q !== false) {
                    $d['err_' . $vel['foldername']]['foldername'] = $vel['foldername'];
                    $d['err_' . $vel['foldername']]['ip'] = $k;
                    $d['err_' . $vel['foldername']]['protocol'] = $vel['protocol'];
                    $d['err_' . $vel['foldername']]['err'] = trim($q);
                } else {
                    $d[$fn]['foldername'] = $vel['foldername'];
                    $d[$fn]['ip'] = $k;
                    $d[$fn]['protocol'] = $vel['protocol'];
                }
            }
        }
        return $d;
    }

    /**
     * @param $selected_url MediaURL
     * @return string encoded path
     */
    public static function set_folder_info(&$selected_url)
    {
        if (!isset($selected_url->ip_path) || $selected_url->ip_path === false) {
            $save_folder['filepath'] = $selected_url->filepath;
        } else if ($selected_url->nfs_protocol !== false) {
            $save_folder[$selected_url->ip_path]['foldername'] = preg_replace("|^/tmp/mnt/network/\d*|", '', $selected_url->filepath);
        } else {
            $save_folder[$selected_url->ip_path]['foldername'] = preg_replace("|^/tmp/mnt/smb/\d*|", '', $selected_url->filepath);
            $save_folder[$selected_url->ip_path]['user'] = isset($selected_url->user) ? $selected_url->user : false;
            $save_folder[$selected_url->ip_path]['password'] = isset($selected_url->password) ? $selected_url->password : false;
        }

        return json_encode($save_folder);
    }

    /**
     * @param string $encoded_data
     * @param string|null $default
     * @return string // real path with trailing '/'
     */
    public static function get_folder_info($encoded_data, $default = null)
    {
        if ($default === null) {
            $default = get_data_path();
        }

        if (empty($encoded_data)) {
            return $default;
        }

        $settings = json_decode($encoded_data, true);
        if (isset($settings['filepath'])) {
            $select_folder = $settings['filepath'];
        } else {
            $select_folder = '';
            foreach ($settings as $item) {
                if (isset($item['foldername'])) {
                    $q = isset($item['user']) ? self::get_mount_smb($settings) : self::get_mount_nfs();
                    $select_folder = key($q) . $item['foldername'];
                    break;
                }
            }
        }

        if (empty($select_folder)) {
            $select_folder = $default;
        }

        if (substr($select_folder, -1) !== '/') {
            $select_folder .= '/';
        }

        return $select_folder;
    }
}

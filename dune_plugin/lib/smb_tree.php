<?php
/**
 * The MIT License (MIT)
 *
 * @Author: Andrii Kopyniak
 * Modification and improvements: sharky72 (https://github.com/KocourKuba)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

require_once 'hd.php';

class smb_tree
{
    const PARAM_FOLDERNAME = 'foldername';
    const PARAM_USER = 'user';
    const PARAM_PASSWORD = 'password';
    const PARAM_IP = 'ip';
    const PARAM_ID = 'id';
    const PARAM_SERVER = 'server';
    const PARAM_DIRECTORY = 'directory';
    const PARAM_IP_PATH = 'ip_path';
    const PARAM_NFS_PROTOCOL = 'nfs_protocol';
    const PARAM_ERR = 'err';
    const PARAM_PROTOCOL = 'protocol';

    const PROTOCOL_TCP = 'tcp';
    const PROTOCOL_UDP = 'udp';
    const NETWORK_CONFIG = '/config/network_folders.properties';

    private $descriptor_spec;
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

    /**
     * @param MediaURL $selected_url
     * @return string encoded path
     */
    public static function set_folder_info(&$selected_url)
    {
        if (!isset($selected_url->{self::PARAM_IP_PATH}) || $selected_url->{self::PARAM_IP_PATH} === false) {
            $save_folder[PARAM_FILEPATH] = $selected_url->{PARAM_FILEPATH};
        } else if ($selected_url->{self::PARAM_NFS_PROTOCOL} !== false) {
            $save_folder[$selected_url->{self::PARAM_IP_PATH}][self::PARAM_FOLDERNAME] = preg_replace(
                "|^/tmp/mnt/network/\d*|", '', $selected_url->{PARAM_FILEPATH});
        } else {
            $save_folder[$selected_url->{self::PARAM_IP_PATH}][self::PARAM_FOLDERNAME] = preg_replace(
                "|^/tmp/mnt/smb/\d*|", '', $selected_url->{PARAM_FILEPATH});
            $save_folder[$selected_url->{self::PARAM_IP_PATH}][self::PARAM_USER] = safe_get_value($selected_url, self::PARAM_USER,  false);
            $save_folder[$selected_url->{self::PARAM_IP_PATH}][self::PARAM_PASSWORD] = safe_get_value($selected_url, self::PARAM_PASSWORD, false);
        }

        return json_encode($save_folder);
    }

    /**
     * @param string $encoded_data smb json encoded or plain path
     * @param string|null $default
     * @return string
     */
    public static function get_folder_info($encoded_data, $default = null)
    {
        if (empty($encoded_data)) {
            return $default;
        }

        // if data not encoded json_decode returns NULL
        $settings = @json_decode($encoded_data, true);
        if (empty($settings)) {
            $select_folder = $encoded_data;
        } else if (isset($settings[PARAM_FILEPATH])) {
            $select_folder = $settings[PARAM_FILEPATH];
        } else {
            $select_folder = '';
            foreach ($settings as $item) {
                if (isset($item[self::PARAM_FOLDERNAME])) {
                    $q = isset($item[self::PARAM_USER]) ? self::get_mount_smb($settings) : self::get_mount_nfs();
                    $select_folder = key($q) . $item[self::PARAM_FOLDERNAME];
                    break;
                }
            }
        }

        return empty($select_folder) ? $default : $select_folder;
    }

    public static function get_mount_smb($ip_smb)
    {
        $mounts = array();
        foreach ($ip_smb as $k => $vel) {
            $df_smb = self::get_df_smb();
            if (isset($df_smb[str_replace(array('/', '\134'), '', $k)])) {
                $mounts['/tmp/mnt/smb/' . $df_smb[str_replace(array('/', '\134'), '', $k)]][self::PARAM_FOLDERNAME] = $vel[self::PARAM_FOLDERNAME];
                $mounts['/tmp/mnt/smb/' . $df_smb[str_replace(array('/', '\134'), '', $k)]][self::PARAM_IP] = $k;
                if (!empty($vel[self::PARAM_USER])) {
                    $mounts['/tmp/mnt/smb/' . $df_smb[str_replace(array('/', '\134'), '', $k)]][self::PARAM_USER] = $vel[self::PARAM_USER];
                }

                if (!empty($vel[self::PARAM_PASSWORD])) {
                    $mounts['/tmp/mnt/smb/' . $df_smb[str_replace(array('/', '\134'), '', $k)]][self::PARAM_PASSWORD] = $vel[self::PARAM_PASSWORD];
                }
            } else {
                $ret_code = false;
                $n = count($df_smb);
                $username = 'guest';
                $password = '';
                if (!empty($vel[self::PARAM_USER])) {
                    $username = $vel[self::PARAM_USER];
                }

                if (!empty($vel[self::PARAM_PASSWORD])) {
                    $password = $vel[self::PARAM_PASSWORD];
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
                    if (!create_path($fn)) {
                        hd_debug_print("Directory '$fn' was not created");
                    }
                    $exec_string = "mount -t cifs -o username=$username,password=$password,posixpaths,rsize=32768,wsize=130048 \"$k\" \"$fn\" 2>&1 &";
                    hd_debug_print("Mount string: $exec_string", true);
                    $ret_code = exec($exec_string);
                } else {
                    $fn = $wr;
                }

                if ($ret_code !== false) {
                    $mounts['err_' . $vel[self::PARAM_FOLDERNAME]][self::PARAM_FOLDERNAME] = $vel[self::PARAM_FOLDERNAME];
                    $mounts['err_' . $vel[self::PARAM_FOLDERNAME]][self::PARAM_IP] = $k;
                    $mounts['err_' . $vel[self::PARAM_FOLDERNAME]][self::PARAM_ERR] = trim($ret_code);

                    if (!empty($vel[self::PARAM_USER])) {
                        $mounts['err_' . $vel[self::PARAM_FOLDERNAME]][self::PARAM_USER] = $vel[self::PARAM_USER];
                    }

                    if (!empty($vel[self::PARAM_PASSWORD])) {
                        $mounts['err_' . $vel[self::PARAM_FOLDERNAME]][self::PARAM_PASSWORD] = $vel[self::PARAM_PASSWORD];
                    }
                } else {
                    $mounts[$fn][self::PARAM_FOLDERNAME] = $vel[self::PARAM_FOLDERNAME];
                    $mounts[$fn][self::PARAM_IP] = $k;
                    if (!empty($vel[self::PARAM_USER])) {
                        $mounts[$fn][self::PARAM_USER] = $vel[self::PARAM_USER];
                    }
                    if (!empty($vel[self::PARAM_PASSWORD])) {
                        $mounts[$fn][self::PARAM_PASSWORD] = $vel[self::PARAM_PASSWORD];
                    }
                }
            }
        }
        return $mounts;
    }

    /*
     * @return 0 if success
     */

    public static function get_df_smb()
    {
        $df_smb = array();
        $out_mount = file_get_contents("/proc/mounts");
        /** @var array $m */
        if (preg_match_all('|(.+)/tmp/mnt/smb/(.+?) |', $out_mount, $m)) {
            foreach ($m[2] as $k => $v) {
                $df_smb[str_replace(array('/', '\134'), '', $m[1][$k])] = $v;
            }
        }
        return $df_smb;
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
            safe_unlink($tmp_path);
            return false;
        }

        $path = "$dir_path/$cmd_name.$pid-$cmd_id.res";
        sleep(1);
        if (!file_exists($path)) {
            return false;
        }

        $res = parse_ini_file($path, true);
        safe_unlink($path);
        $path = '/tmp/run/network_mount_list.xml';
        if (!file_exists($path)) {
            return false;
        }

        $xml = simplexml_load_string(file_get_contents($path));
        if ($xml === false) {
            hd_debug_print("Error parsing $path.");
            return false;
        }

        foreach ($xml->children() as $elt) {
            $elt_name = $elt->getName();
            if ($elt_name !== 'mount') {
                continue;
            }

            $id = (int)$elt['id'];
            $path = (string)$elt['path'];
            $type = (string)$elt['type'];
            if (($id === (int)$res['id']) && ($type === $params['type'])) {
                return $path;
            }
        }

        return false;
    }

    public static function get_mount_nfs()
    {
        $d = array();
        $ip_nfs = self::get_network_folder_nfs();
        $df_nfs = self::get_df_nfs();
        foreach ($ip_nfs as $k => $vel) {
            if (isset($df_nfs[$k])) {
                $d['/tmp/mnt/network/' . $df_nfs[$k]][self::PARAM_FOLDERNAME] = $vel[self::PARAM_FOLDERNAME];
                $d['/tmp/mnt/network/' . $df_nfs[$k]][self::PARAM_IP] = $k;
                $d['/tmp/mnt/network/' . $df_nfs[$k]][self::PARAM_PROTOCOL] = $vel[self::PARAM_PROTOCOL];
            } else {
                $q = false;
                $n = count($df_nfs) + 100;
                $wr = self::write_request('network_manager', 'mount', $n,
                    array(
                        'type' => 'nfs',
                        'server' => $vel[self::PARAM_SERVER],
                        'dir' => $vel[self::PARAM_DIRECTORY],
                        'proto' => $vel[self::PARAM_PROTOCOL],
                    ));

                if ($wr === false) {
                    $fn = '/tmp/mnt/network/' . $n;
                    if (!create_path($fn)) {
                        hd_debug_print("Directory '$fn' was not created");
                    }
                    $q = shell_exec("mount -t nfs -o " . $vel[self::PARAM_PROTOCOL] . " $k $fn 2>&1");
                } else {
                    $fn = $wr;
                }

                if ($q !== false) {
                    $d['err_' . $vel[self::PARAM_FOLDERNAME]][self::PARAM_FOLDERNAME] = $vel[self::PARAM_FOLDERNAME];
                    $d['err_' . $vel[self::PARAM_FOLDERNAME]][self::PARAM_IP] = $k;
                    $d['err_' . $vel[self::PARAM_FOLDERNAME]][self::PARAM_PROTOCOL] = $vel[self::PARAM_PROTOCOL];
                    $d['err_' . $vel[self::PARAM_FOLDERNAME]][self::PARAM_ERR] = trim($q);
                } else {
                    $d[$fn][self::PARAM_FOLDERNAME] = $vel[self::PARAM_FOLDERNAME];
                    $d[$fn][self::PARAM_PROTOCOL] = $vel[self::PARAM_PROTOCOL];
                    $d[$fn][self::PARAM_IP] = $k;
                }
            }
        }
        return $d;
    }

    public static function get_network_folder_nfs()
    {
        $nfs = array();
        $network_folder = self::parse_network_config();
        if (count($network_folder) > 0) {
            foreach ($network_folder as $v) {
                if ((int)$v[PARAM_TYPE] !== 1) continue;

                $p = ((int)$v[self::PARAM_PROTOCOL] === 1) ? self::PROTOCOL_TCP : self::PROTOCOL_UDP;
                $nfs[$v[self::PARAM_SERVER] . ':' . $v[self::PARAM_DIRECTORY]][self::PARAM_PROTOCOL] = $p;
                $nfs[$v[self::PARAM_SERVER] . ':' . $v[self::PARAM_DIRECTORY]][self::PARAM_FOLDERNAME] = $v[PARAM_NAME];
                $nfs[$v[self::PARAM_SERVER] . ':' . $v[self::PARAM_DIRECTORY]][self::PARAM_SERVER] = $v[self::PARAM_SERVER];
                $nfs[$v[self::PARAM_SERVER] . ':' . $v[self::PARAM_DIRECTORY]][self::PARAM_DIRECTORY] = $v[self::PARAM_DIRECTORY];
            }
        }

        return $nfs;
    }

    protected static function parse_network_config()
    {
        $network_folder = array();
        if (file_exists(self::NETWORK_CONFIG)) {
            $network = parse_ini_file(self::NETWORK_CONFIG, true);
            foreach ($network as $k => $v) {
                /** @var array $m */
                if (preg_match("/(.*)\.(.*)/", $k, $m)) {
                    $network_folder[$m[2]][$m[1]] = $v;
                }
            }
        }
        return $network_folder;
    }

    public static function get_df_nfs()
    {
        $df_nfs = array();
        $out_mount = file_get_contents("/proc/mounts");
        /** @var array $m */
        if (preg_match_all('|(.+) /tmp/mnt/network/(.+?) |', $out_mount, $m)) {
            foreach ($m[2] as $k => $v) {
                $df_nfs[$m[1][$k]] = $v;
            }
        }
        return $df_nfs;
    }

    public function get_mount_all_smb($info)
    {
        switch ($info) {
            case 1:
                // only network folders
                $ip = self::get_ip_network_folder_smb();
                break;
            case 2:
                // network folders and network folders + SMB search
                $ip = safe_merge_array($this->get_ip_server_shares_smb(), self::get_ip_network_folder_smb());
                break;
            case 3:
                // only SMB search
                $ip = $this->get_ip_server_shares_smb();
                break;
            default:
                $ip = array();
        }

        return self::get_mount_smb($ip);
    }

    public static function get_ip_network_folder_smb()
    {
        $d = array();
        $network_folder_smb = self::get_network_folder_smb();
        foreach ($network_folder_smb as $k => $v) {
            if (!preg_match('/((25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(25[0-5]|2[0-4]\d|[01]?\d\d?)/', $k)) {
                $out = shell_exec(self::get_nmblookup_path() . ' "' . $k . '" -S');
                /** @var array $m */
                if (preg_match('/(.*) (.*)<00>/', $out, $m)) {
                    $ip = '//' . $m[1] . '/';
                    if ($m[2] === (string)$k) {
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

    public static function get_network_folder_smb()
    {
        $d = array();
        $network_folder = self::parse_network_config();
        if (count($network_folder) > 0) {
            foreach ($network_folder as $v) {
                if ((int)$v['type'] !== 0) continue;

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

        return $d;
    }

    public static function get_nmblookup_path()
    {
        $cmd = '&&$FS_PREFIX/firmware_ext/smbserver/bin/nmblookup --configfile=$FS_PREFIX/etc/samba/smb.conf';
        if (file_exists("/firmware_ext/smbserver/lib")) {
            // android
            $path = 'export LD_LIBRARY_PATH=$FS_PREFIX/firmware_ext/smbserver/lib:$FS_PREFIX/firmware/lib:$LD_LIBRARY_PATH';
        } else if (file_exists("/firmware/bin/nmblookup")) {
            // sigma
            $path = 'export LD_LIBRARY_PATH=/firmware/lib:$LD_LIBRARY_PATH';
            $cmd = '&&/firmware/bin/nmblookup';
        } else {
            $path = 'export LD_LIBRARY_PATH=$FS_PREFIX/lib:$FS_PREFIX/firmware/lib:$LD_LIBRARY_PATH';
        }

        hd_debug_print("nmblookup: $path$cmd");
        return $path . $cmd;
    }

    public function get_ip_server_shares_smb()
    {
        $d = array();
        $my_ip = get_ip_address();
        $server_shares_smb = $this->get_server_shares_smb();
        foreach ($server_shares_smb as $k => $v) {
            //hd_debug_print("server shares: $k");
            $out = shell_exec(self::get_nmblookup_path() . ' "' . $k . '" -R');
            /** @var array $m */
            if (preg_match('/(.*) (.*)<00>/', $out, $m)) {
                if ($my_ip === $m[1]) {
                    continue;
                }

                $ip = '//' . $m[1] . '/';
                if ($m[2] === (string)$k) {
                    foreach ($v as $key => $vel) {
                        $vel[self::PARAM_FOLDERNAME] = $key . ' in ' . $k;
                        $d[$ip . $key] = $vel;
                    }
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

    public function get_xdomains()
    {
        return ($this->execute('-X') !== 0) ? array() : self::parse_smbtree_output($this->smb_tree_output);
    }

    private function execute($args = '')
    {
        $cmd = '$FS_PREFIX' . "/firmware/bin/smbtree {$this->get_auth_options()} {$this->get_debug_level()} $args";
        hd_debug_print("smbtree exec: $cmd", true);
        $env = array('LD_LIBRARY_PATH' => '$FS_PREFIX/firmware/lib');
        /** @var array $pipes */
        $process = proc_open($cmd, $this->descriptor_spec, $pipes, '/tmp', $env);

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

    private function get_auth_options()
    {
        return ($this->is_no_pass()) ? '-N' : '';
    }

    private function is_no_pass()
    {
        return $this->no_pass;
    }

    private function get_debug_level()
    {
        return '--debuglevel ' . $this->debug_level;
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
                    $q = safe_get_value($detail_info,1, '');
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

    public function get_workgroup_servers($domain)
    {
        return ($this->execute('-W ' . $domain) !== 0) ? array() : self::parse_smbtree_output($this->smb_tree_output);
    }

    public function get_server_shares($server)
    {
        return ($this->execute('-E ' . $server) !== 0) ? array() : self::parse_smbtree_output($this->smb_tree_output);
    }
}

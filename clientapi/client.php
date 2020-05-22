<?php
/*  Backend for PhilleConnect client-programs
    © 2017 - 2020 Johannes Kreutz.*/
    include "api/dbconnect.php";
    //load global functions for accessing global config
    include "api/accessConfig.php";
    //load logging functions
    include "api/logging.php";
    //global password is to separate two installations (e.g. testing and production) in the same network
    if ($_POST['globalpw'] == loadConfig('globalPw', null)) {
        //check if MAC-adress is registred
        $getCountRequest = "SELECT COUNT(*) AS anzahl FROM machines WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['machine'])."'";
        $getCountQuery = mysqli_query($database, $getCountRequest);
        $getCountResult = mysqli_fetch_assoc($getCountQuery);
        if ($getCountResult['anzahl'] > 0) {
            //machine is registred -> load profile depending on operating system
            if ($_POST['os'] == 'win') {
                $getConfigRequest = "SELECT config_win, ip, room, machine, teacher FROM machines WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['machine'])."'";
            } else {
                $getConfigRequest = "SELECT config_linux, ip, room, machine, teacher FROM machines WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['machine'])."'";
            }
        } else if ($getCountResult['anzahl'] == 0) {
            //machine is not registred
            echo "nomachine";
            die;
        }
        $getConfigQuery = mysqli_query($database, $getConfigRequest);
        $getConfigResult = mysqli_fetch_assoc($getConfigQuery);
        if ($_POST['ip'] != $_SERVER['REMOTE_ADDR']) {
            echo 'noaccess';
            die;
        } else {
            //update stored IP if changed
            if ($getConfigResult['ip'] != $_POST['ip']) {
                $updateRequest = "UPDATE machines SET ip = '".mysqli_real_escape_string($database, $_POST['ip'])."' WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['machine'])."'";
                $updateQuery = mysqli_query($database, $updateRequest);
            }
        }
        if ($_POST['usage'] == 'userlist') {
            //create user list and deliver it as JSON
            $ldapconn = ldap_connect(loadConfig('ldap', 'url'));
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
            $r=ldap_bind($ldapconn);
            $allusers=ldap_search($ldapconn, loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), "uid=*");
            $users = ldap_get_entries($ldapconn, $allusers);
            if ($getConfigResult['teacher'] == '1' && $_POST['sort'] != 'students') {
                $group=ldap_search($ldapconn, loadConfig('ldap', 'groupsdn').','.loadConfig('ldap', 'basedn'), loadConfig('ldap', 'teacherscn'));
            } else {
                $group=ldap_search($ldapconn, loadConfig('ldap', 'groupsdn').','.loadConfig('ldap', 'basedn'), loadConfig('ldap', 'studentscn'));
            }
            $groupcontent = ldap_get_entries($ldapconn, $group);
            $data = array();
            for ($i=0; $i<$users['count']; $i++) {
                if (in_array($users[$i]['cn'][0], $groupcontent[0]['memberuid'])) {
                    $parameter = array($users[$i]['givenname'][0], $users[$i]['sn'][0], $users[$i]['cn'][0]);
                    array_push($data, $parameter);
                }
            }
            sort($data);
            $data = (object)$data;
            echo json_encode($data);
        } elseif ($_POST['usage'] == 'login') {
            //login. lock internet if necessary
            $request = "SELECT inet FROM machines WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['machine'])."'";
            $query = mysqli_query($database, $request);
            $response = mysqli_fetch_assoc($query);
            $inetRequest = "UPDATE machines SET ipfire = ".$response['inet']." WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['machine'])."'";
            $inetQuery = mysqli_query($database, $inetRequest);
            $machinesRequest = "SELECT machine, ip, ipfire FROM machines";
            $machinesQuery = mysqli_query($database, $machinesRequest);
            $customhosts = fopen("customhosts", "w");
            $customgroups = fopen("customgroups", "w");
            $counter = 1;
            while ($result = mysqli_fetch_assoc($machinesQuery)) {
                fwrite($customhosts, $counter.",".$result['machine'].",ip,".$result['ip']."/255.255.255.255\n");
                if ($result['ipfire'] == 0) {
                    fwrite($customgroups, $counter.",blocked,,".$result['machine'].",Custom Host\n");
                }
                $counter++;
            }
            fclose($customhosts);
            fclose($customgroups);
            //copy files via SCP to IPFire and reload them via SSH
            $connection = ssh2_connect(loadConfig('ipfire', 'url'), loadConfig('ipfire', 'port'));
            ssh2_auth_password($connection, 'philleconnect', loadConfig('ipfire', 'password'));
            ssh2_scp_send($connection, 'customhosts', '/var/ipfire/fwhosts/customhosts', 0644);
            ssh2_scp_send($connection, 'customgroups', '/var/ipfire/fwhosts/customgroups', 0644);
            ssh2_exec($connection, '/usr/local/bin/firewallctrl');
            //login
            $ldapconn = ldap_connect(loadConfig('ldap', 'url'));
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
            $r=ldap_bind($ldapconn);
            $allusers=ldap_search($ldapconn, loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), "uid=".$_POST['uname']);
            $users = ldap_get_entries($ldapconn, $allusers);
            if ($getConfigResult['teacher'] == '1') {
                $group=ldap_search($ldapconn, loadConfig('ldap', 'groupsdn').','.loadConfig('ldap', 'basedn'), loadConfig('ldap', 'teacherscn'));
                $groupcontent = ldap_get_entries($ldapconn, $group);
            }
            if ($users['count'] < 1) {
                addLogEntry(101, $_POST['uname'], $_POST['machine'], '');
                echo '2';
            } else {
                if ($getConfigResult['teacher'] != '1') {
                    if (@ldap_bind($ldapconn, 'uid='.$_POST['uname'].','.loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), $_POST['password'])) {
                        addLogEntry(100, $_POST['uname'], $_POST['machine'], '');
                        echo '0';
                    } else {
                        addLogEntry(101, $_POST['uname'], $_POST['machine'], '');
                        echo '1';
                    }
                } elseif (in_array($users[0]['cn'][0], $groupcontent[0]['memberuid'])) {
                    if (@ldap_bind($ldapconn, 'uid='.$_POST['uname'].','.loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), $_POST['password'])) {
                        addLogEntry(100, $_POST['uname'], $_POST['machine'], '');
                        echo '0';
                    } else {
                        addLogEntry(101, $_POST['uname'], $_POST['machine'], '');
                        echo '1';
                    }
                } else {
                    addLogEntry(103, $_POST['uname'], $_POST['machine'], '');
                    echo '2';
                }
            }
        } elseif ($_POST['usage'] == 'pwchange') {
            //change user password
            $ldapconn = ldap_connect(loadConfig('ldap', 'url'));
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
            $r=ldap_bind($ldapconn);
            $allusers=ldap_search($ldapconn, loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), "uid=".$_POST['uname']);
            $users = ldap_get_entries($ldapconn, $allusers);
            if ($users['count'] < 1) {
                echo 'error';
            } else {
                if (@ldap_bind($ldapconn, 'uid='.$_POST['uname'].','.loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), $_POST['oldpw'])) {
                    if ($_POST['newpw'] == $_POST['newpw2']) {
                        $salt = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 4)), 0, 4);
                        $hash = '{SSHA}' . base64_encode(sha1($_POST['newpw'].$salt, TRUE).$salt);
                        $sambaHash = strtoupper(bin2hex(mhash(MHASH_MD4, iconv("UTF-8","UTF-16LE",$_POST['newpw']))));
                        $now = time();
                        $entry = array();
                        $entry['userPassword'] = $hash;
                        $entry['sambaNTPassword'] = $sambaHash;
                        $entry['sambaPwdLastSet'] = $now;
                        ldap_bind($ldapconn, loadConfig('ldap', 'admindn').','.loadConfig('ldap', 'basedn'), loadConfig('ldap', 'password'));
                        if (ldap_modify($ldapconn, "uid=".$_POST['uname'].", ".loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), $entry)) {
                            addLogEntry(200, $_POST['uname'], $_POST['machine'], '');
                            echo 'success';
                        } else {
                            echo 'error';
                        }
                    } else {
                        echo 'notsame';
                    }
                } else {
                    echo 'wrongold';
                }
            }
        } elseif ($_POST['usage'] == 'pwreset') {
            //reset students passwort with teachers access rights
            if ($getConfigResult['teacher'] == '1') {
                $ldapconn = ldap_connect(loadConfig('ldap', 'url'));
                ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
                $r=ldap_bind($ldapconn);
                $allusers=ldap_search($ldapconn, loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), "uid=".$_POST['teacheruser']);
                $users = ldap_get_entries($ldapconn, $allusers);
                $group=ldap_search($ldapconn, loadConfig('ldap', 'groupsdn').','.loadConfig('ldap', 'basedn'), loadConfig('ldap', 'teacherscn'));
                $groupcontent = ldap_get_entries($ldapconn, $group);
                if ($users['count'] < 1) {
                    echo 'error';
                } else {
                    if (in_array($users[0]['cn'][0], $groupcontent[0]['memberuid'])) {
                        $nallusers=ldap_search($ldapconn, loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), "uid=".$_POST['uname']);
                        $nusers = ldap_get_entries($ldapconn, $nallusers);
                        if (in_array($nusers[0]['cn'][0], $groupcontent[0]['memberuid'])) {
                            echo 'notallowed';
                        } else {
                            if (@ldap_bind($ldapconn, 'uid='.$_POST['teacheruser'].','.loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), $_POST['teacherpw'])) {
                                if ($_POST['newpw'] == $_POST['newpw2']) {
                                    $salt = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 4)), 0, 4);
                                    $hash = '{SSHA}' . base64_encode(sha1($_POST['newpw'].$salt, TRUE).$salt);
                                    $sambaHash = strtoupper(bin2hex(mhash(MHASH_MD4, iconv("UTF-8","UTF-16LE",$_POST['newpw']))));
                                    $now = time();
                                    $entry = array();
                                    $entry['userPassword'] = $hash;
                                    $entry['sambaNTPassword'] = $sambaHash;
                                    $entry['sambaPwdLastSet'] = $now;
                                    ldap_bind($ldapconn, loadConfig('ldap', 'admindn').','.loadConfig('ldap', 'basedn'), loadConfig('ldap', 'password'));
                                    if (ldap_modify($ldapconn, "uid=".$_POST['uname'].", ".loadConfig('ldap', 'usersdn').','.loadConfig('ldap', 'basedn'), $entry)) {
                                        addLogEntry(201, $_POST['teacheruser'], $_POST['machine'], $_POST['uname']);
                                        echo 'success';
                                    } else {
                                        echo 'error';
                                    }
                                } else {
                                    echo 'notsame';
                                }
                            } else {
                                echo 'wrongteacher';
                            }
                        }
                    } else {
                        echo 'noteacher';
                    }
                }
            } else {
                echo 'noaccess';
            }
        } elseif ($_POST['usage'] == 'notices') {
            //deliver notes
            if ($_POST['os'] == 'win') {
                $request = "SELECT infotext FROM configs WHERE name = '".mysqli_real_escape_string($database, $getConfigResult['config_win'])."'";
            } else {
                $request = "SELECT infotext FROM configs WHERE name = '".mysqli_real_escape_string($database, $getConfigResult['config_linux'])."'";
            }
            $query = mysqli_query($database, $request);
            $result = mysqli_fetch_assoc($query);
            echo $result['infotext'];
        } elseif ($_POST['usage'] == 'config') {
            //create config and deliver it as JSON
            if ($_POST['os'] == 'win') {
                $isConfigThereRequest = "SELECT COUNT(*) AS anzahl FROM configs WHERE name = '".mysqli_real_escape_string($database, $getConfigResult['config_win'])."'";
            } else {
                $isConfigThereRequest = "SELECT COUNT(*) AS anzahl FROM configs WHERE name = '".mysqli_real_escape_string($database, $getConfigResult['config_linux'])."'";
            }
            $isConfigThereQuery = mysqli_query($database, $isConfigThereRequest);
            $isConfigThereResult = mysqli_fetch_assoc($isConfigThereQuery);
            if ($isConfigThereResult['anzahl'] <= 0) {
                echo 'noconfig';
            } else {
                if ($_POST['os'] == 'win') {
                    if ($getConfigResult['config_win'] != null && $getConfigResult['config_win'] != '') {
                        $request = "SELECT * FROM configs WHERE name = '".mysqli_real_escape_string($database, $getConfigResult['config_win'])."'";
                    } else {
                        echo 'noconfig';
                    }
                } else {
                    if ($getConfigResult['config_linux'] != null && $getConfigResult['config_linux'] != '') {
                        $request = "SELECT * FROM configs WHERE name = '".mysqli_real_escape_string($database, $getConfigResult['config_linux'])."'";
                    } else {
                        echo 'noconfig';
                    }
                }
                $query = mysqli_query($database, $request);
                $result = mysqli_fetch_assoc($query);
                $groupfolders = json_decode($result['groupfolders']);
                $i = 0;
                foreach ($groupfolders as $gf) {
                    $gfrequest = "SELECT name FROM groupfolders WHERE id = ".mysqli_real_escape_string($database, $gf[1]);
                    $gfquery = mysqli_query($database, $gfrequest);
                    $gfresponse = mysqli_fetch_assoc($gfquery);
                    $groupfolders[$i][1] = $gfresponse['name'];
                    $i++;
                }
                $groupfolders = (object)$groupfolders;
                $groupfolders = json_encode($groupfolders);
                $data = array();
                array_push($data, array('dologin', $result['dologin']),
                    array('loginpending', $result['loginpending']),
                    array('loginfailed', $result['loginfailed']),
                    array('wrongcredentials', $result['wrongcredentials']),
                    array('networkfailed', $result['networkfailed']),
                    array('success', $result['success']),
                    array('shutdown', $result['shutdown']),
                    array('smbserver', $result['smbserver']),
                    array('driveone', $result['driveone']),
                    array('drivetwo', $result['drivetwo']),
                    array('drivethree', $result['drivethree']),
                    array('pathone', $result['pathone']),
                    array('pathtwo', $result['pathtwo']),
                    array('paththree', $result['paththree']),
                    array('infotext', $result['infotext']),
                    array('room', $getConfigResult['room']),
                    array('machinename', $getConfigResult['machine']),
                    array('groupfolders', $groupfolders));
                if ($result['servicemode'] == '1') {
                    array_push($data, array('servicemode', 'noPasswordRequired'));
                } else {
                    array_push($data, array('servicemode', 'disabled'));
                }
                $data = (object)$data;
                echo json_encode($data);
            }
        } elseif ($_POST['usage'] == 'internet') {
            //lock / unlock internet for single machine / whole room
            if ($getConfigResult['teacher'] == '1') {
                if ($_POST['lock'] == '1') {
                    if ($_POST['task'] == 'room') {
                        $inetRequest = "UPDATE machines SET ipfire = 1 WHERE room = '".mysqli_real_escape_string($database, $getConfigResult['room'])."' AND teacher = 0";
                    } else {
                        $inetRequest = "UPDATE machines SET ipfire = 1 WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['target'])."' AND teacher = 0";
                    }
                } else {
                    if ($_POST['task'] == 'room') {
                        $inetRequest = "UPDATE machines SET ipfire = 0 WHERE room = '".mysqli_real_escape_string($database, $getConfigResult['room'])."' AND teacher = 0";
                    } else {
                        $inetRequest = "UPDATE machines SET ipfire = 0 WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['target'])."' AND teacher = 0";
                    }
                }
                $inetQuery = mysqli_query($database, $inetRequest);
                $request = "SELECT machine, ip, ipfire FROM machines";
                $query = mysqli_query($database, $request);
                $customhosts = fopen("customhosts", "w");
                $customgroups = fopen("customgroups", "w");
                $counter = 1;
                while ($result = mysqli_fetch_assoc($query)) {
                    fwrite($customhosts, $counter.",".$result['machine'].",ip,".$result['ip']."/255.255.255.255\n");
                    if ($result['ipfire'] == 0) {
                        fwrite($customgroups, $counter.",blocked,,".$result['machine'].",Custom Host\n");
                    }
                    $counter++;
                }
                fclose($customhosts);
                fclose($customgroups);
                //copy files via SCP to IPFire and reload them via SSH
                $connection = ssh2_connect(loadConfig('ipfire', 'url'), loadConfig('ipfire', 'port'));
                ssh2_auth_password($connection, 'philleconnect', loadConfig('ipfire', 'password'));
                ssh2_scp_send($connection, 'customhosts', '/var/ipfire/fwhosts/customhosts', 0644);
                ssh2_scp_send($connection, 'customgroups', '/var/ipfire/fwhosts/customgroups', 0644);
                ssh2_exec($connection, '/usr/local/bin/firewallctrl');
            } else {
                echo 'noaccess';
            }
        } elseif ($_POST['usage'] == 'roomlist') {
            //create list of all machines in a room for teacherclient
            if ($getConfigResult['teacher'] == '1') {
                $request = "SELECT room, machine, hardwareid, ip, ipfire FROM machines WHERE room = '".mysqli_real_escape_string($database, $getConfigResult['room'])."' AND teacher = '0'";
                $query = mysqli_query($database, $request);
                $data = array();
                while ($response = mysqli_fetch_assoc($query)) {
                    $machineData = array($response['room'], $response['machine'], $response['ip'], $response['hardwareid'], $response['ipfire']);
                    array_push($data, $machineData);
                }
                sort($data);
                $data = (object)$data;
                echo json_encode($data);
            } else {
                echo 'noaccess';
            }
        } elseif ($_POST['usage'] == 'wake') {
            //wake machine with wake on lan
            if ($getConfigResult['teacher'] == '1') {
                shell_exec('wakeonlan -i'.$_POST['targetIp'].' -p 9 '.$_POST['targetMac']);
            } else {
                echo 'noaccess';
            }
        } elseif ($_POST['usage'] == 'checkteacher') {
            $teacherRequest = "SELECT teacher FROM machines WHERE ip = '".mysqli_real_escape_string($database, $_POST['req'])."'";
            $teacherQuery = mysqli_query($database, $teacherRequest);
            $teacherResponse = mysqli_fetch_assoc($teacherQuery);
            if ($teacherResponse['teacher'] == '1') {
                echo 'success';
            } else {
                echo 'noaccess';
            }
        } elseif ($_POST['usage'] == 'checkinet') {
            $chkInetRequest = "SELECT ipfire FROM machines WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['hwaddr'])."'";
            $chkInetQuery = mysqli_query($database, $chkInetRequest);
            $chkInetResult = mysqli_fetch_assoc($chkInetQuery);
            echo $chkInetResult['ipfire'];
        }
    } else {
        echo '!';
    }
?>

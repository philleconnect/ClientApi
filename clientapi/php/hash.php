<?php
    function unix($input) {
        $salt = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 4)), 0, 4);
        return '{SSHA}' . base64_encode(sha1($input.$salt, TRUE).$salt);
    }
    function samba($input) {
        return strtoupper(bin2hex(mhash(MHASH_MD4, iconv("UTF-8", "UTF-16LE", $input))));
    }
    function check_unix($password, $hash) {
        if ($hash == "") {
            return false;
        }
        $salt = substr(base64_decode(substr($hash,6)),20);
        $encrypted_password = '{SSHA}' . base64_encode(sha1( $password.$salt, TRUE ). $salt);
        if ($hash == $encrypted_password) return true;
        return false;
    }
?>

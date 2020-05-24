<?php
    function unix($input) {
        $salt = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 4)), 0, 4);
        return '{SSHA}' . base64_encode(sha1($input.$salt, TRUE).$salt);
    }
    function samba($input) {
        return strtoupper(bin2hex(mhash(MHASH_MD4, iconv("UTF-8", "UTF-16LE", $input))));
    }

?>

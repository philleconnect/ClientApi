<?php
    function unix($input) {
        return password_hash($input, PASSWORD_BCRYPT);
    }
    function samba($input) {
        return strtoupper(bin2hex(mhash(MHASH_MD4, iconv("UTF-8", "UTF-16LE", $input))));
    }
?>

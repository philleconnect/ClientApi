<?php
    require_once "config.php";
    $database = new mysqli("main_db", MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
    if ($database->connect_errno) {
        echo "Datenbankfehler. Wir bitten dies zu entschuldigen. Reason: ".$database->connect_errno;
        exit();
    }
    $database->set_charset('utf8');
?>

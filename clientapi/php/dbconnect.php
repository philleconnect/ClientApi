<?php
    $database = new mysqli("main_db", $_ENV["MYSQL_USER"], $_ENV["MYSQL_PASSWORD"], $_ENV["MYSQL_DATABASE"]);
    if ($database->connect_errno) {
        echo "Datenbankfehler. Wir bitten dies zu entschuldigen. Reason: ".$database->connect_errno;
        exit();
    }
    $database->set_charset('utf8');
?>

<?php
/*  Backend for PhilleConnect client registration
    © 2017 - 2020 Johannes Kreutz.*/
    require "dbconnect.php";
    require "functions.php";
    if (!isUserUnique($_POST["username"])) {
        echo "error";
        die;
    }
    if (!checkUserPassword($_POST["username"], $_POST["pw"])) {
        echo "noaccess";
        die;
    }
    if (!in_array("devimgmt", getUserPermissions($_POST["username"]))) {
        echo "noaccess";
        die;
    }
    // Check if we already have a machine with this mac address
    $machineStmt = $database->prepare("SELECT COUNT(*) AS num FROM hardwareidentifier WHERE address = ?");
    $machineStmt->bind_param("s", $_POST["mac"]);
    if (!$machineStmt->execute()) {
        echo "error";
        die;
    }
    $machineResult = $machineStmt->get_result()->fetch_assoc();
    if ($machineResult['anzahl'] > 0) {
        echo "notnew";
        die;
    }
    // Check if we have a config profile named by the room
    $roomStmt = $database->prepare("SELECT COUNT(*) AS num FROM devprofile WHERE name = ?");
    $roomStmt->bind_param("s", $_POST["room"]);
    if (!$roomStmt->execute()) {
        echo "error";
        die;
    }
    $roomResult = $roomStmt->get_result()->fetch_assoc();
    $insertMachineStmt = $database->prepare("INSERT INTO device (name, networklock, lastknownIPv4, requiresLogin, room, registered, devprofile_id) VALUES (?, ?, ?, 1, ?, now(), ?)");
    $insertMachineStmt->bind_param("sissi", $_POST["name"], $_POST["inet"], $_POST["ip"], $_POST["room"], $_POST["profile_id"]);
    if (!$insertMachineStmt->execute()) {
        echo "error";
        die;
    }
    $insertIdentifierStmt = $database->prepare("INSERT INTO hardwareidentifier (address, type, device_id) VALUES (?, 1, ?)");
    $insertIdentifierStmt->bind_param("si", $_POST["mac"], $insertMachineStmt->insert_id);
    if (!$insertIdentifierStmt->execute()) {
        echo "error";
        die;
    }
    echo "success";
?>

<?php
/*  Backend for PhilleConnect client registration
    © 2017 - 2020 Johannes Kreutz.*/
    require "dbconnect.php";
    require "hash.php";
    require "functions.php";
    if (!isUserUnique($_POST["uname"])) {
        echo "error";
        die;
    }
    if (!checkUserPassword($_POST["uname"], $_POST["pw"])) {
        echo "noaccess";
        die;
    }
    if (!in_array("devimgmt", getUserPermissions($_POST["uname"], $_POST["mac"]))) {
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
    if ($machineResult['num'] > 0) {
        echo "notnew";
        die;
    }
    // Check if we have a config profile named by the room
    $roomStmt = $database->prepare("SELECT COUNT(*) AS num, id FROM devprofile WHERE name = ?");
    $roomStmt->bind_param("s", $_POST["room"]);
    if (!$roomStmt->execute()) {
        echo "error";
        die;
    }
    $roomResult = $roomStmt->get_result()->fetch_assoc();
    $willSetProfile = false;
    if ($roomResult["num"] == 1) {
        $willSetProfile = true;
        $insertMachineStmt = $database->prepare("INSERT INTO device (name, networklock, lastknownIPv4, requiresLogin, room, registered, devprofile_id, teacher) VALUES (?, ?, ?, 1, ?, now(), ?, ?)");
        $insertMachineStmt->bind_param("sissii", $_POST["name"], $_POST["inet"], $_POST["ip"], $_POST["room"], $roomResult["id"], $_POST["teacher"]);
    } else {
        $exampleProfileName = ($_POST["teacher"] == 1) ? "Beispielprofil Lehrercomputer" : "Beispielprofil Schülercomputer";
        $exampleProfileStmt = $database->prepare("SELECT COUNT(*) AS num, id FROM devprofile WHERE name = ?");
        $exampleProfileStmt->bind_param("s", $exampleProfileName);
        if (!$exampleProfileStmt->execute()) {
            echo "error";
            die;
        }
        $exampleProfileResult = $exampleProfileStmt->get_result()->fetch_assoc();
        if ($exampleProfileResult["num"] == 1) {
            $willSetProfile = true;
            $insertMachineStmt = $database->prepare("INSERT INTO device (name, networklock, lastknownIPv4, requiresLogin, room, registered, devprofile_id, teacher) VALUES (?, ?, ?, 1, ?, now(), ?, ?)");
            $insertMachineStmt->bind_param("sissii", $_POST["name"], $_POST["inet"], $_POST["ip"], $_POST["room"], $exampleProfileResult["id"], $_POST["teacher"]);
        } else {
            $insertMachineStmt = $database->prepare("INSERT INTO device (name, networklock, lastknownIPv4, requiresLogin, room, registered, teacher) VALUES (?, ?, ?, 1, ?, now(), ?)");
            $insertMachineStmt->bind_param("sissi", $_POST["name"], $_POST["inet"], $_POST["ip"], $_POST["room"], $_POST["teacher"]);
        }
    }
    if (!$insertMachineStmt->execute()) {
        echo "error";
        die;
    }
    $newId = $insertMachineStmt->insert_id;
    $insertIdentifierStmt = $database->prepare("INSERT INTO hardwareidentifier (address, device_id, type) VALUES (?, ?, 1)");
    $insertIdentifierStmt->bind_param("si", $_POST["mac"], $newId);
    if (!$insertIdentifierStmt->execute()) {
        echo "error";
        die;
    }
    echo $willSetProfile ? "success_room_both" : "success";
?>

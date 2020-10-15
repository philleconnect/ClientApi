<?php
/*  Backend for PhilleConnect client-programs
    © 2017 - 2020 Johannes Kreutz.*/
    require "dbconnect.php";
    // Load logging functions
    require "logging.php";
    // Load hashing functions
    require "hash.php";
    // Load some useful functions
    require "functions.php";
    // Load config vars
    require_once "config.php";
    // Global password is to separate two installations (e.g. testing and production) in the same network
    if ($_POST["globalpw"] != GLOBAL_PASSWORD) {
        echo "!";
        die;
    }
    // Check if MAC-adress is registered
    if (!isMacRegistered($_POST["machine"])) {
        echo "nomachine";
        die;
    }
    // Load machines configuration profile
    $config = loadMachineData($_POST["machine"]);
    // Check if client reports correct ip
    if ($_POST["ip"] != $_SERVER["REMOTE_ADDR"]) {
        echo "noaccess";
        die;
    } else {
        // Update stored IP if changed
        if ($config["lastknownIPv4"] != $_POST["ip"]) {
            updateIp($config["id"], $_POST["ip"]);
        }
    }
    // Switch request
    switch($_POST["usage"]) {
        case "userlist":
            $allowedGroups = $_POST["sort"] == "students" ? getPermissionGroups("pwalwrst") : getDeviceProfileGroups($config["devprofile_id"]);
            $groupString = "";
            foreach ($allowedGroups as $group) {
                $groupString .= $groupString == "" ? "(".$group : ",".$group;
            }
            if ($groupString != "") $groupString .= ")";
            $stmt = $database->prepare("SELECT DISTINCT firstname, lastname, username FROM people P INNER JOIN people_has_groups PHG ON PHG.people_id = P.id WHERE PHG.group_id IN ".$groupString);
            if (!$stmt->execute()) {
                echo "error";
                die;
            }
            $data = array();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                array_push($data, array($row["firstname"], $row["lastname"], $row["username"]));
            }
            sort($data);
            $data = (object)$data;
            echo json_encode($data);
            die;
        case "login":
            $stmt = $database->prepare("SELECT DP.networklockDefault, D.id FROM devprofile DP INNER JOIN device D ON D.devprofile_id = DP.id INNER JOIN hardwareidentifier HWI ON HWI.device_id = D.id WHERE HWI.address = ?");
            $stmt->bind_param("s", $_POST["machine"]);
            if (!$stmt->execute()) {
                echo "error";
                die;
            }
            $response = $stmt->get_result()->fetch_assoc();
            $updateStmt = $database->prepare("UPDATE device SET networklock = ? WHERE id = ?");
            $updateStmt->bind_param("ii", $response["networklockDefault"], $response["id"]);
            if (!$updateStmt->execute()) {
                echo "error";
                die;
            }
            updateIpfire();
            if (!isUserUnique($_POST["uname"])) {
                echo "2";
                die;
            }
            $id = loadUserId($_POST["uname"]);
            $permissions = getUserPermissions($_POST["uname"], $_POST["machine"]);
            if ($config["teacher"] == "1" && in_array("teachlgn", $permissions) || $config["teacher"] == "0" && in_array("studelgn", $permissions)) {
                if (!checkUserPassword($_POST["uname"], $_POST["password"])) {
                    addLoginLog($id, $_POST["machine"], 10);
                    echo "1";
                    die;
                } else {
                    addLoginLog($id, $_POST["machine"], 0);
                    echo "0";
                    die;
                }
            }
            addLoginLog($id, $_POST["machine"], 11);
            echo "2";
            die;
        case "pwchange":
            if (!isUserUnique($_POST["uname"])) {
                echo "error";
                die;
            }
            $id = loadUserId($_POST["uname"]);
            if (!checkUserPassword($_POST["uname"], $_POST["oldpw"])) {
                addPasswordChangeLog($id, $_POST["machine"], 10);
                echo "wrongold";
                die;
            }
            if ($_POST['newpw'] !== $_POST['newpw2']) {
                addPasswordChangeLog($id, $_POST["machine"], 11);
                echo "notsame";
                die;
            }
            $stmt = $database->prepare("UPDATE userpassword SET unix_hash = ?, smb_hash = ? WHERE people_id = ?");
            $stmt->bind_param("sss", $unix = unix($_POST["newpw"]), $samba = samba($_POST["newpw"]), $id);
            if (!$stmt->execute()) {
                addPasswordChangeLog($id, $_POST["machine"], 1);
                echo "error";
                die;
            }
            if (!updateUser($id)) {
                addPasswordChangeLog($id, $_POST["machine"], 2);
                echo "error";
                die;
            }
            addPasswordChangeLog($id, $_POST["machine"], 0);
            echo "success";
            die;
        case "pwreset":
            if (!isUserUnique($_POST["teacheruser"]) || !isUserUnique($_POST["uname"])) {
                echo "error";
                die;
            }
            $id = loadUserId($_POST["teacheruser"]);
            $targetId = loadUserId($_POST["uname"]);
            if ($config["teacher"] != "1") {
                addPasswordResetLog($targetId, $_POST["machine"], $id, 14);
                echo "noaccess";
                die;
            }
            if (!in_array("teachlgn", getUserPermissions($_POST["teacheruser"], $_POST["machine"]))) {
                addPasswordResetLog($targetId, $_POST["machine"], $id, 12);
                echo "noteacher";
                die;
            }
            if (in_array("teachlgn", getUserPermissions($_POST["uname"], $_POST["machine"]))) {
                addPasswordResetLog($targetId, $_POST["machine"], $id, 13);
                echo "notallowed";
                die;
            }
            if (!checkUserPassword($_POST["teacheruser"], $_POST["teacherpw"])) {
                addPasswordResetLog($targetId, $_POST["machine"], $id, 10);
                echo "wrongteacher";
                die;
            }
            if ($_POST['newpw'] !== $_POST['newpw2']) {
                addPasswordResetLog($targetId, $_POST["machine"], $id, 11);
                echo "notsame";
                die;
            }
            $stmt = $database->prepare("UPDATE userpassword SET unix_hash = ?, smb_hash = ? WHERE people_id = ?");
            $stmt->bind_param("sss", $unix = unix($_POST["newpw"]), $samba = samba($_POST["newpw"]), $targetId);
            if (!$stmt->execute()) {
                addPasswordResetLog($targetId, $_POST["machine"], $id, 1);
                echo "error";
                die;
            }
            if (!updateUser($targetId)) {
                addPasswordResetLog($targetId, $_POST["machine"], $id, 2);
                echo "error";
                die;
            }
            addPasswordResetLog($targetId, $_POST["machine"], $id, 0);
            echo "success";
            die;
        case "notices":
            // Deliver notes
            $stmt = $database->prepare("SELECT comment FROM devprofile WHERE id = ?");
            $stmt->bind_param("i", $config["devprofile_id"]);
            if (!$stmt->execute()) {
                echo "error";
                die;
            }
            echo $stmt->get_result()->fetch_assoc()["comment"];
            die;
        case "config":
            if ($config["devprofile_id"] == null) {
                echo "noconfig";
                die;
            }
            $stmt = $database->prepare("SELECT * FROM devprofile WHERE id = ?");
            $stmt->bind_param("i", $config["devprofile_id"]);
            if (!$stmt->execute()) {
                echo "error";
                die;
            }
            $result = $stmt->get_result()->fetch_assoc();
            $groupfolderStmt = $database->prepare("SELECT name FROM shares S INNER JOIN devprofile_has_shares DHS ON DHS.shares_id = S.id WHERE DHS.devprofile_id = ?");
            $groupfolderStmt->bind_param("i", $config["devprofile_id"]);
            if (!$groupfolderStmt->execute()) {
                echo "error";
                die;
            }
            $response = $groupfolderStmt->get_result();
            $groupfolders = array();
            $startingLetter = ord("J");
            while ($row = $response->fetch_assoc()) {
                $letter = chr($startingLetter).":\\";
                $startingLetter++;
                array_push($groupfolders, [$letter, $row["name"]]);
            }
            $groupfolders = (object)$groupfolders;
            $groupfolders = json_encode($groupfolders);
            $data = array();
            array_push($data, array('dologin', "Bitte melde dich mit deinen Zugangsdaten an."),
                array('loginpending', "Anmeldung läuft, bitte warten..."),
                array('loginfailed', "Anmeldung fehlgeschlagen. Bitte frage deinen Lehrer."),
                array('wrongcredentials', "Nutzername oder Passwort falsch."),
                array('networkfailed', "Nutzername falsch oder Netzwerkfehler."),
                array('success', "Anmeldung erfolgreich!"),
                array('shutdown', "300"),
                array('smbserver', HOST_NETWORK_ADDRESS),
                array('driveone', "X:"),
                array('drivetwo', "Y:"),
                array('drivethree', "Z:"),
                array('pathone', "Laufwerk X"),
                array('pathtwo', "Laufwerk Y"),
                array('paththree', "Laufwerk Z"),
                array('infotext', $result['comment']),
                array('room', $config['room']),
                array('machinename', $config['name']),
                array('groupfolders', $groupfolders),
                array('isteachermachine', strval($config["teacher"])));
            if ($config['requiresLogin'] == '0') {
                array_push($data, array('servicemode', 'noPasswordRequired'));
            } else {
                array_push($data, array('servicemode', 'disabled'));
            }
            $data = (object)$data;
            echo json_encode($data);
            die;
        case "internet":
            if ($config["teacher"] != "1") {
                echo "noaccess";
                die;
            }
            $id = loadDeviceId($_POST["target"]);
            $lock = $_POST["lock"] == '1' ? 1 : 0;
            if ($_POST["task"] == "room") {
                $stmt = $database->prepare("UPDATE device SET networklock = ? WHERE room = ? AND teacher = 0");
                $stmt->bind_param("si", $_POST["roomlist"], $id);
            } else {
                $stmt = $database->prepare("UPDATE device SET networklock = ? WHERE id = ? AND teacher = 0");
                $stmt->bind_param("ii", $lock, $id);
            }
            if (!$stmt->execute()) {
                echo "error";
                die;
            }
            updateIpfire();
            die;
        case "roomlist":
            if ($config["teacher"] != "1") {
                echo "noaccess";
                die;
            }
            $stmt = $database->prepare("SELECT room, name, address, lastknownIPv4, networklock FROM device D INNER JOIN hardwareidentifier HWI ON HWI.device_id = D.id WHERE room = ? AND teacher = '0'");
            $stmt->bind_param("s", $config["room"]);
            if (!$stmt->execute()) {
                echo "error";
                die;
            }
            $data = array();
            $result = $stmt->get_result();
            while ($response = $result->fetch_assoc()) {
                $machineData = array($response['room'], $response['name'], $response['lastknownIPv4'], $response['address'], $response['networklock']);
                array_push($data, $machineData);
            }
            sort($data);
            $data = (object)$data;
            echo json_encode($data);
            die;
        case "wake":
            if ($config['teacher'] == '1') {
                shell_exec('wakeonlan -i'.$_POST['targetIp'].' -p 9 '.$_POST['targetMac']);
            } else {
                echo 'noaccess';
            }
            die;
        case "checkteacher":
            $stmt = $database->prepare("SELECT teacher FROM device WHERE lastknownIPv4 = ?");
            $stmt->bind_param("s", $_POST["req"]);
            if (!$stmt->execute()) {
                echo "noaccess";
            }
            $response = $stmt->get_result()->fetch_assoc();
            echo $response["teacher"] == 1 ? "success" : "noaccess";
            die;
        case "checkinet":
            $stmt = $database->prepare("SELECT networklock FROM device D INNER JOIN hardwareidentifier HW ON D.id = HW.device_id WHERE HW.address = ?");
            $stmt->bind_param("s", $_POST["hwaddr"]);
            if (!$stmt->execute()) {
                echo "error";
            }
            echo $stmt->get_result()->fetch_assoc()['networklock'];
            die;
        default:
            echo "!";
    }
?>

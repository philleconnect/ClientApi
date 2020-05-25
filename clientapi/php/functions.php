<?php
    // Listst the permissions for a given username
    function getUserPermissions($username, $mac) {
        global $database;
        $did = loadDeviceId($mac);
        $stmt = $database->prepare("SELECT P.detail, G.id FROM permission P INNER JOIN groups_has_permission GHP ON P.id = GHP.permission_id INNER JOIN groups G ON GHP.group_id = G.id INNER JOIN people_has_groups PHG ON G.id = PHG.group_id INNER JOIN people PPL ON PHG.people_id = PPL.id WHERE PPL.username = ?");
        $stmt->bind_param("s", $username);
        if ($stmt->execute()) {
            $permissions = [];
            $response = $stmt->get_result();
            while ($row = $response->fetch_assoc()) {
                //if ($did == $row["id"]) {
                    array_push($permissions, $row["detail"]);
                //}
            }
            return $permissions;
        } else {
            return false;
        }
    }
    // Verifies a user password
    function checkUserPassword($username, $password) {
        global $database;
        $stmt = $database->prepare("SELECT unix_hash FROM userpassword UP INNER JOIN people P ON UP.people_id = P.id WHERE P.username = ?");
        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            return false;
        }
        $response = $stmt->get_result();
        $result = $response->fetch_assoc();
        if (password_verify($password, $result["unix_hash"])) {
            return true;
        }
        return false;
    }
    // Returns if the given username is unique
    function isUserUnique($username) {
        global $database;
        $stmt = $database->prepare("SELECT COUNT(*) AS num FROM people WHERE username = ?");
        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            return false;
        }
        $response = $stmt->get_result();
        $result = $response->fetch_assoc();
        return $result["num"] == 1;
    }
    // Returns if the given mac address belongs to a registered machine
    function isMacRegistered($mac) {
        global $database;
        $stmt = $database->prepare("SELECT COUNT(*) AS num FROM hardwareidentifier WHERE address = ?");
        $stmt->bind_param("s", $mac);
        if (!$stmt->execute()) {
            return false;
        }
        $response = $stmt->get_result();
        $result = $response->fetch_assoc();
        return $result["num"] == 1;
    }
    // Loads the user id for a given username
    function loadUserId($suername) {
        global $database;
        $stmt = $database->prepare("SELECT COUNT(*) AS num, id FROM people WHERE username = ?");
        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            return false;
        }
        $response = $stmt->get_result();
        $result = $response->fetch_assoc();
        return $result["num"] == 1 ? $result["id"] : false;
    }
    // Loads the device id for a given mac address
    function loadDeviceId($mac) {
        global $database;
        $stmt = $database->prepare("SELECT id FROM device D INNER JOIN hardwareidentifier HWI ON HWI.device_id = D.id WHERE HWI.address = ?");
        $stmt->bind_param("s", $mac);
        if (!$stmt->execute()) {
            return false;
        }
        $response = $stmt->get_result();
        $result = $response->fetch_assoc();
        return $result["id"];
    }
    // Returns data for a given mac address
    function loadMachineData($mac) {
        global $database;
        $stmt = $database->prepare("SELECT name, comment, registered, networklock, room, requiresLogin, lastknownIPv4, devprofile_id, teacher FROM devices D INNER JOIN hardwareidentifier HWI ON HWI.device_id = D.id WHERE HWI.address = ?");
        $stmt->bind_param("s", $mac);
        if (!$stmt->execute()) {
            return false;
        }
        $response = $stmt->get_result();
        return $response->fetch_assoc();
    }
    // Updates IP address for a given machine id
    function updateIp($machine, $ip) {
        global $database;
        $stmt = $database->prepare("UPDATE devices SET lastknownIPv4 = ? WHERE id = ?");
        $stmt->bind_param("si", $machine, $ip);
        return $stmt->execute();
    }
    // Returns all groups of a device profile
    function getDeviceProfileGroups($profile_id) {
        global $database;
        $stmt = $database->prepare("SELECT group_id FROM devprofile_has_groups WHERE devprofile_id = ?");
        $stmt->bind_param("i", $profile_id);
        if (!$stmt->execute()) {
            return false;
        }
        $groups = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            array_push($groups, $row["group_id"]);
        }
        return $groups;
    }
    // Calls user update function on main backend
    function updateUser($uid) {
        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query([])
            )
        );
        $context = stream_context_create($options);
        return file_get_contents("http://pc_admin:84/api/public/usercheck/".$id, false, $context) == "SUCCESS";
    }
    // Calls ipfire update function on main backend
    function updateIpfire() {
        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query([])
            )
        );
        $context = stream_context_create($options);
        return file_get_contents("http://pc_admin:84/api/public/ipfire", false, $context) == "SUCCESS";
    }
?>

<?php
    // Listst the permissions for a given username
    function getUserPermissions($username) {
        $stmt = $database->prepare("SELECT PE.name FROM permission P INNER JOIN groups_has_permission GPH ON P.id = GHP.permission_id INNER JOIN groups P ON GHP.group_id = G.id INNER JOIN people_has_groups PHG ON G.id = PHG.group_id INNER JOIN people P ON PHG.people_id = P.id WHERE P.username = ?");
        $stmt->bind_param("s", $username);
        if ($stmt->execute()) {
            $permissions = [];
            while ($row = $stmt->get_result()->fetch_assoc()) {
                array_push($permissions, $row["name"]);
            }
            return $permissions;
        } else {
            return false;
        }
    }
    // Verifies a user password
    function checkUserPassword($username, $password) {
        $stmt = $database->prepare("SELECT unix_hash FROM userpassword UP INNER JOIN people P ON UP.people_id = P.id WHERE P.username = ?");
        $stmt->bind_param("s", $username);
        $result = $stmt->get_result()->fetch_assoc();
        if (password_verify($password, $result["unix_hash"])) {
            return true;
        }
        return false;
    }
    // Returns if the given username is unique
    function isUserUnique($username) {
        $stmt = $database->prepare("SELECT COUNT(*) AS num FROM people WHERE username = ?");
        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            return false;
        }
        $result = $stmt->get_result()->fetch_assoc();
        return $result["num"] == 1;
    }
    // Returns if the given mac address belongs to a registered machine
    function isMacRegistered($mac) {
        $stmt = $database->prepare("SELECT COUNT(*) AS num FROM hardwareidentifier WHERE address = ?");
        $stmt->bind_param("s", $mac);
        if (!$stmt->execute()) {
            return false;
        }
        $result = $stmt->get_result()->fetch_assoc();
        return $result["num"] == 1;
    }
    // Returns data for a given mac address
    function loadConfigProfile($mac) {
        $stmt = $database->prepare("SELECT name, comment, registered, networklock, room, requiresLogin, lastknownIPv4, devprofile_id, teacher FROM devices D INNER JOIN hardwareidentifier HWI ON HWI.device_id = D.id WHERE HWI.address = ?");
        $stmt->bind_param("s", $mac);
        if (!$stmt->execute()) {
            return false;
        }
        return $stmt->get_result()->fetch_assoc();
    }
    // Updates IP address for a given machine id
    function updateIp($machine, $ip) {
        $stmt = $database->prepare("UPDATE devices SET lastknownIPv4 = ? WHERE id = ?");
        $stmt->bind_param("si", $machine, $ip);
        return $stmt->execute();
    }
    // Returns all groups of a device profile
    function getDeviceProfileGroups($profile_id) {
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
?>

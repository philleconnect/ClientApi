<?php
    function logEntry($uid, $machine, $text, $type) {
        global $database;
        $id = loadDeviceId($machine);
        $stmt = $database->prepare("INSERT INTO localLoginLog (people_id, device_id, timestamp, info, type) VALUES (?, ?, now(), ?, ?)");
        $stmt->bind_param("sisi", $uid, $id, $text, $type);
        if (!$stmt->execute()) {
            return false;
        }
        return true;
    }
    function addLoginLog($uid, $machine, $status) {
        logEntry($uid, $machine, strval($status), 0);
    }
    function addPasswordChangeLog($uid, $machine, $status) {
        logEntry($uid, $machine, strval($status), 1);
    }
    function addPasswordResetLog($uid, $machine, $master, $status) {
        global $database;
        $id = loadDeviceId($machine);
        $stmt = $database->prepare("INSERT INTO localLoginLog (people_id, device_id, timestamp, info, type, affected) VALUES (?, ?, now(), ?, ?, ?)");
        $stmt->bind_param("sisi", $uid, $id, strval($status), $type, $master);
        if (!$stmt->execute()) {
            return false;
        }
        return true;
    }
?>

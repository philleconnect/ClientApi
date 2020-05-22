<?php
/*  Backend for PhilleConnect client registration
    © 2017 - 2020 Johannes Kreutz.*/
    include "api/dbconnect.php";
    $accessRequest = "SELECT password FROM userdata WHERE username = '".mysqli_real_escape_string($database, $_POST['uname'])."' AND type = 1 OR type = 3";
    $accessQuery = mysqli_query($database, $accessRequest);
    $accessResponse = mysqli_fetch_assoc($accessQuery);
    if ($accessResponse['password'] == hash('sha512', $_POST['pw'])) {
        $machineRequest = "SELECT COUNT(*) AS anzahl FROM machines WHERE hardwareid = '".mysqli_real_escape_string($database, $_POST['mac'])."'";
        $machineQuery = mysqli_query($database, $machineRequest);
        $machineResult = mysqli_fetch_assoc($machineQuery);
        if ($machineResult['anzahl'] > 0) {
            echo "notnew";
        } else {
            $roomRequestWin = "SELECT COUNT(*) AS anzahl FROM configs WHERE name = '".mysqli_real_escape_string($database, $_POST['room'])."' AND os = 'win'";
            $roomQueryWin = mysqli_query($database, $roomRequestWin);
            $roomResultWin = mysqli_fetch_assoc($roomQueryWin);
            $roomRequestLinux = "SELECT COUNT(*) AS anzahl FROM configs WHERE name = '".mysqli_real_escape_string($database, $_POST['room'])."' AND os = 'linux'";
            $roomQueryLinux = mysqli_query($database, $roomRequestLinux);
            $roomResultLinux = mysqli_fetch_assoc($roomQueryLinux);
            if ($roomResultWin['anzahl'] == 1 && $roomResultLinux['anzahl'] == 1) {
                $request = "INSERT INTO machines (room, machine, hardwareid, ip, inet, teacher, ipfire, config_win, config_linux) VALUES ('".mysqli_real_escape_string($database, $_POST['room'])."', '".mysqli_real_escape_string($database, $_POST['name'])."', '".mysqli_real_escape_string($database, $_POST['mac'])."', '".mysqli_real_escape_string($database, $_POST['ip'])."', '".mysqli_real_escape_string($database, $_POST['inet'])."', '".mysqli_real_escape_string($database, $_POST['teacher'])."', '".mysqli_real_escape_string($database, $_POST['inet'])."', '".mysqli_real_escape_string($database, $_POST['room'])."', '".mysqli_real_escape_string($database, $_POST['room'])."')";
            } else if ($roomResultWin['anzahl'] == 1) {
                $request = "INSERT INTO machines (room, machine, hardwareid, ip, inet, teacher, ipfire, config_win, config_linux) VALUES ('".mysqli_real_escape_string($database, $_POST['room'])."', '".mysqli_real_escape_string($database, $_POST['name'])."', '".mysqli_real_escape_string($database, $_POST['mac'])."', '".mysqli_real_escape_string($database, $_POST['ip'])."', '".mysqli_real_escape_string($database, $_POST['inet'])."', '".mysqli_real_escape_string($database, $_POST['teacher'])."', '".mysqli_real_escape_string($database, $_POST['inet'])."', '".mysqli_real_escape_string($database, $_POST['room'])."', 'beispiel_linux')";
            } else if ($roomResultLinux['anzahl'] == 1) {
                $request = "INSERT INTO machines (room, machine, hardwareid, ip, inet, teacher, ipfire, config_win, config_linux) VALUES ('".mysqli_real_escape_string($database, $_POST['room'])."', '".mysqli_real_escape_string($database, $_POST['name'])."', '".mysqli_real_escape_string($database, $_POST['mac'])."', '".mysqli_real_escape_string($database, $_POST['ip'])."', '".mysqli_real_escape_string($database, $_POST['inet'])."', '".mysqli_real_escape_string($database, $_POST['teacher'])."', '".mysqli_real_escape_string($database, $_POST['inet'])."', 'beispiel_windows', '".mysqli_real_escape_string($database, $_POST['room'])."')";
            } else {
                $request = "INSERT INTO machines (room, machine, hardwareid, ip, inet, teacher, ipfire, config_win, config_linux) VALUES ('".mysqli_real_escape_string($database, $_POST['room'])."', '".mysqli_real_escape_string($database, $_POST['name'])."', '".mysqli_real_escape_string($database, $_POST['mac'])."', '".mysqli_real_escape_string($database, $_POST['ip'])."', '".mysqli_real_escape_string($database, $_POST['inet'])."', '".mysqli_real_escape_string($database, $_POST['teacher'])."', '".mysqli_real_escape_string($database, $_POST['inet'])."', 'beispiel_windows', 'beispiel_linux')";
            }
            $query = mysqli_query($database, $request);
            if ($query == true) {
                if ($roomResultWin['anzahl'] == 1 && $roomResultLinux['anzahl'] == 1) {
                    echo "success_room_both";
                } else if ($roomResultWin['anzahl'] == 1) {
                    echo "success_room_win";
                } else if ($roomResultLinux['anzahl'] == 1) {
                    echo "success_room_linux";
                } else {
                    echo "success";
                }
            } else {
                echo "error";
            }
        }
    } else {
        echo "noaccess";
    }
?>

<?php

require_once('config.php');

#ini_set('display_startup_errors',1);
#ini_set('display_errors',1);
#error_reporting(-1);


$auth=0;

if ($_REQUEST['auth'] && (!preg_match('/^[a-zA-Z0-9]+$/',$_REQUEST['auth']))) {
	exit;
}
$token=$_REQUEST['auth'];
if ($token) {
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("select count(uid),week,admin from users where authkey=? and enabled=1");
	$stmt->bind_param("s", $token);
	$stmt->execute();
	$stmt->bind_result($auth,$week,$is_admin);
	$stmt->fetch();
}

if ($auth<1) {
	exit;
}

$data = json_decode(file_get_contents('php://input'));

if (preg_match('/Home/',$data->location)) {
	if ($data->event == "LocationEnter") {
		$location = "1";
	} else {
		$location = "0";
	}
}

if ($location >= 0) {
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("update users set isHome=?,homeTime=NOW() where authkey=?");
	$stmt->bind_param("ss", $location,$token);
	$stmt->execute();
}
?>

<?php

require_once('config.php');

#ini_set('display_startup_errors',1);
#ini_set('display_errors',1);
#error_reporting(-1);


$auth=0;
$event=-1;
$location=0;

if ($_REQUEST['auth'] && (!preg_match('/^[a-zA-Z0-9]+$/',$_REQUEST['auth']))) {
	exit;
}
$token=$_REQUEST['auth'];
if ($token) {
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("select count(uid),week,admin,user from users where authkey=? and enabled=1");
	$stmt->bind_param("s", $token);
	$stmt->execute();
	$stmt->bind_result($auth,$week,$is_admin,$user);
	$stmt->fetch();
}

if ($auth<1) {
	exit;
}

$data = json_decode(file_get_contents('php://input'));

if (preg_match('/Home/',$data->location)) {
	$location = 1;
	if ($data->event == "LocationEnter") {
		$event = "1";
	} else {
		$event= "0";
	}
}

if (!$data && $_REQUEST['fix'] == 1) {
	$location=1;
	$event = 1;
}

if ($event >= 0) {
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("update users set isHome=?,homeTime=NOW() where authkey=?");
	$stmt->bind_param("ss", $event,$token);
	$stmt->execute();

	if ($location === 1) {  # if event impacts Home..
		$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
		$stmt = $conn->prepare("select sum(isHome) from users where enabled=1");
        	$stmt->execute();
        	$stmt->bind_result($count);
		$stmt->fetch();

		#echo "$count:$event:$location";
		
	}
}
?>

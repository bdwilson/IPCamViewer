<?php

require_once('config.php');

if (preg_match($ip_net,$_SERVER['REMOTE_ADDR'])) {
	$auth=1;
}
if ($_REQUEST['auth'] && (!preg_match('/^[a-zA-Z0-9]+$/',$_REQUEST['auth']))) {
	exit;
}
if ($_REQUEST['snapshot'] && (!preg_match('/^[0-9]+$/',$_REQUEST['snapshot']))) {
	exit;
}

if ($_REQUEST['auth']) {
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("select count(uid) from users where authkey=? and enabled=1");
	$stmt->bind_param("s", $_REQUEST['auth']);
	$stmt->execute();
	$stmt->bind_result($auth);
	$stmt->fetch();
}   


$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
$stmt = $conn->prepare("select snapshot_url from cameras where cid=?");
$stmt->bind_param("s", $_REQUEST['snapshot']);
$stmt->execute();
$stmt->bind_result($url);
$stmt->fetch();

if ($auth > 0 && $url) {
	header("Content-type: image/jpeg");
	$image= file_get_contents($url);
	echo $image;
}
?>

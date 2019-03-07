<?php
require_once('config.php');


if ($_REQUEST['auth'] && (!preg_match('/^[a-zA-Z0-9]+$/',$_REQUEST['auth']))) {
	exit;
}
if (($_REQUEST['snapshot'] > 0) && (!preg_match('/^[0-9]+$/',$_REQUEST['snapshot']))) {
	exit;
}

$auth = 0;
$token=$_REQUEST['auth'];
$snapshot=$_REQUEST['snapshot'];

if ($token) {
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("select count(uid),week,admin from users where authkey=? and enabled=1");
	$stmt->bind_param("s", $token);
	$stmt->execute();
	$stmt->bind_result($auth,$week,$is_admin);
	$stmt->fetch();
    $stmt->close();
	#if ($auth >= 1) {
	#	$auth = 1;
	#}
}

if ($auth<1) {
	exit;
}

$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
$stmt = $conn->prepare("select snapshot_url from cameras where cid=?");
$stmt->bind_param("s", $snapshot);
$stmt->execute();
$stmt->bind_result($url);
$stmt->fetch();

if ($url) {
        header("Content-type: image/jpeg");
        $image= file_get_contents($url);
        echo $image;
}
?>

<?php
# 
# Needs this app installed via SmartThings or Hubitat: https://github.com/bdwilson/hubitat/blob/master/NotifyIfMotion/NotifyIfMotion-App.groovy 
# Make sure you point the above app to this endpoint AND you'll need to pass
# along one of your auth strings for one of your users to authenticate the
# endpoint. For instance: if your user key is "BBBBAAAABBB" then you point it
# to http://yourdynamic.dnsname.org/hubitat.php?auth=BBBBAAAABBB
#
# You'll also need to configure the PIR endpoints in the cameras table in your database so that the
# displayName of the device matches the name in the database. Note, the .groovy
# code above allows for spaces and will remove those from names posted to the
# API. If you're reading this and made it this far, kudos. -B
# 
require_once('config.php');

#ini_set('display_startup_errors',1);
#ini_set('display_errors',1);
#error_reporting(-1);


$auth=0;
$location=-1;

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

#$data = json_decode(file_get_contents('php://input'));
#$data = file_get_contents('php://input');
$pirname = $_REQUEST['displayName'];
$status = $_REQUEST['value'];

if ($status == "active" && $pirname) {
	print $pirname;
	print $status;
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("update cameras set pirTime=NOW() where pirName=?");
	$stmt->bind_param("s", $pirname);
	$stmt->execute();
}
?>

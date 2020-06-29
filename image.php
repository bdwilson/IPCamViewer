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
$stmt = $conn->prepare("select snapshot_url,isAmcrest from cameras where cid=?");
$stmt->bind_param("s", $_REQUEST['snapshot']);
$stmt->execute();
$stmt->bind_result($url,$isAmcrest);
$stmt->fetch();

if ($auth > 0 && $url) {
	header("Content-type: image/jpeg");
	if ($isAmcrest == 1) {
		$image = getAmcrest($url);
	} else {
		$image= file_get_contents($url);
	}
	echo $image;
}

function getAmcrest ($url) {
  if (preg_match('/\/\/([^\:]+):([^\@]+)\@(.*)/', $url, $match)) {
    #echo "$match[1] $match[2]";
	$username = $match[1];
	$password = $match[2];
	$nUrl = "http://" . $match[3];
  } else {
	$nUrl = $url;
  }

  $options = array(
          CURLOPT_URL            => $nUrl,
          CURLOPT_HEADER         => false,
          CURLOPT_VERBOSE        => false,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_SSL_VERIFYPEER => false,    // for https
          CURLOPT_USERPWD        => $username . ":" . $password,
          CURLOPT_HTTPAUTH       => CURLAUTH_DIGEST
  );

  $ch = curl_init();

  curl_setopt_array( $ch, $options );

  try {
    $raw  = curl_exec( $ch );

    // validate CURL status
    if(curl_errno($ch))
        throw new Exception(curl_error($ch), 500);

    // validate HTTP status code (user/password credential issues)
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status_code != 200)
        throw new Exception("Response with Status Code [" . $status_code . "].", 500);

  } catch(Exception $ex) {
      if ($ch != null) curl_close($ch);
      throw new Exception($ex);
  }

  if ($ch != null) curl_close($ch);
  return $raw;
}

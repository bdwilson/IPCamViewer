<?php

require_once('config.php');

#ini_set('display_startup_errors',1);
#ini_set('display_errors',1);
#error_reporting(-1);

$auth=0;
#$curtime=strtotime("now");
$curtime = date('Y-m-d H:i:s');

if (preg_match($ip_net,$_SERVER['REMOTE_ADDR'])) {
	$auth=1;
}
if ($_REQUEST['auth'] && (!preg_match('/^[a-zA-Z0-9]+$/',$_REQUEST['auth']))) {
	exit;
}
if (($_REQUEST['snapshot'] > 0) && (!preg_match('/^[0-9]+$/',$_REQUEST['snapshot']))) {
	exit;
} 
$token=$_REQUEST['auth'];
$snapshot=$_REQUEST['snapshot'];

if ($token) {
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("select count(uid),week from users where authkey=? and enabled=1");
	$stmt->bind_param("s", $token);
	$stmt->execute();
	$stmt->bind_result($auth,$week);
	$stmt->fetch();
}

if ($auth<1) {
	exit;
}

if ($_REQUEST['time'] == "hour") {
	$last = $curtime-3600;
	$last = "1 HOUR";
} else if ($_REQUEST['time'] == "half") {
	$last = $curtime-43200;
	$last = "12 HOUR";
} else if ($_REQUEST['time'] == "day") {
	$last = $curtime-86400;
	$last = "1 DAY";
} else if ($_REQUEST['time'] == "week") {
	$last=$curtime-604800;
	$last = "7 DAY";
} else if ($_REQUEST['time'] == "suppress") {
	$suppress=1;
} else {
	#$last = $curtime-600;
	$last="10 MINUTE";
}

if ($_REQUEST['suppress'] >= 0 && (preg_match('/^[0-9]+$/',$_REQUEST['suppress'])) && $auth) {
	$mins=$_REQUEST['suppress'];	
	$objDateTime = new DateTime('NOW');
	$objDateTime->modify("+$mins minutes");
	$dateFormatted = $objDateTime->format('Y-m-d H:i:s');
	if ($mins == 0) {
		$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
		$stmt = $conn->prepare("delete from suppress where where authkey=?");
		$stmt->bind_param("s",$token);
		$stmt->execute();
		echo "<center>Cleared alert suppression\n";
	}	

	if ($mins>0) {
		$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
		$stmt = $conn->prepare("select authkey from suppress where authkey=?");
		$stmt->bind_param("s",$token);
		$stmt->execute();
        	$stmt->store_result();
        	$count = $stmt->num_rows;
	}
	if ($count>0) {
		$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
		$stmt = $conn->prepare("update suppress set expiration=? where authkey=?");
		$stmt->bind_param("ss", $dateFormatted,$token);
		$stmt->execute();
		echo "<center>Updated alert suppression until $dateFormatted.";
	} else {
		$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
		$stmt = $conn->prepare("insert into suppress VALUES(?,?)");
		$stmt->bind_param("ss", $token,$dateFormatted);
		$stmt->execute();
		echo "<center>Set alert suppression until $dateFormatted.";
	}
	echo "<br><br>";
	
} 
if ($suppress && $token) {
	?>
<center>	Suppress alerts for: [
<a href="index.php?suppress=30&auth=<?=$_REQUEST['auth']?>">30 Minutes</a> |
<a href="index.php?suppress=60&auth=<?=$_REQUEST['auth']?>">1 Hour</a> |
<a href="index.php?suppress=180&auth=<?=$_REQUEST['auth']?>">3 Hours</a> |
<a href="index.php?suppress=360&auth=<?=$_REQUEST['auth']?>">6 Hours</a> | 
<a href="index.php?suppress=720&auth=<?=$_REQUEST['auth']?>">12 Hours</a> | 
<a href="index.php?suppress=1440&auth=<?=$_REQUEST['auth']?>">24 Hours</a> |
<a href="index.php?suppress=0&auth=<?=$_REQUEST['auth']?>">Clear Suppression</a>
 ]
<?php
exit;
}
	
?>
<title>Video Camera Monitor</title>
<center>
[ <a href="index.php?time=suppress&auth=<?=$_REQUEST['auth']?>">Suppress Alerts</a> | 
<a href="index.php?auth=<?=$_REQUEST['auth']?>">Last 10 Minutes</a> | 
<a href="index.php?time=hour&auth=<?=$_REQUEST['auth']?>">Last Hour</a> | 
<a href="index.php?time=half&auth=<?=$_REQUEST['auth']?>">Last 12 Hours</a> | 
<a href="index.php?time=day&auth=<?=$_REQUEST['auth']?>">Last 24 Hours</a>
<?php 
	if ($week == 1) {
?>
| <a href="index.php?time=week&auth=<?=$_REQUEST['auth']?>">Last Week</a> ]
<?php 
	} else {
		echo " ]";
	}
?>
<?php
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("select cid,location from cameras where enabled=1 and snapshot_url != ''");
	#$stmt->bind_param("s", $location);
	$stmt->execute();
	$stmt->bind_result($cid,$location);
	$stmt->store_result();
	$num_of_rows = $stmt->num_rows;
	echo "<br><br>[ ";
	$count = 0;
	while($stmt->fetch()){
		$count++;
		?>
		<a href="index.php?snapshot=<?=$cid?>&auth=<?=$_REQUEST['auth']?>">Snapshot <?=$location?></a> 
		<?php
		if ($num_of_rows > 1 && $count < $num_of_rows) {
			echo " | ";
		}
	}

	?> ] <br> <br><br>
<?php

if ($snapshot > 0) {
	echo "<a border=0 href=image.php?&snapshot=" . $snapshot .  "&auth=". $_REQUEST['auth'] . "><center><img width=100% src=image.php?snapshot=". $snapshot . "&auth=" . $_REQUEST['auth'] . "></a>";
} else {
	$count=0;
	# if someone has viewed the images on the web, mark them as being
	# notified.  
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("update images set notified=1 where notified=0");
	$stmt->execute();
	# 
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$query="select image,date from images where DATE_SUB(NOW(),INTERVAL $last) <= date order by date desc";
	$stmt = $conn->prepare($query);
	#$stmt->bind_param("s", $last);
	$stmt->execute();
	$stmt->bind_result($image,$date);
	#$stmt->fetch();
	while($stmt->fetch()){
	    #echo "$image $date\n<br>";
			$count++;
			$image = $webdir . "/" . $image;
			if ($count == 1) {
				?><table width=100% border=1 cellspacing=0 cellpadding=0><tr>
				<?php
			}
			if ($count % 2) {
			?>
				<td align=center><a border=0 href="<?=$image?>"><img width=100% src="<?=$image?>"></a><br>
			<?php
				#echo date('l m/d/y g:i:s A ', strtotime($key));
				#echo date('l m/d/y g:i:s A ',$key);
				echo $date;
				echo "</td>";
			} else {
			?>
			<td align=center><a border=0 href="<?=$image?>"><img width=100% src="<?=$image?>"></a><br>

			<?php  
				#echo date('l m/d/y g:i:s A ', strtotime($key));
				#echo date('l m/d/y g:i:s A ', $key);
				echo $date;
				echo "</td></tr>";
			}
			#print "$key\n";
	}
	?></table><?php
}

	 
?>

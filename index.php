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
if ($_REQUEST['event'] && (!preg_match('/^[0-9]+$/',$_REQUEST['event']))) {
	exit;
}
if (($_REQUEST['snapshot'] > 0) && (!preg_match('/^[0-9]+$/',$_REQUEST['snapshot']))) {
	exit;
} 
$token=$_REQUEST['auth'];
$snapshot=$_REQUEST['snapshot'];

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

if ($is_admin && $auth && $_REQUEST['admin'] == 1) {
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
	}
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
[ <a href="index.php?events=1&auth=<?=$_REQUEST['auth']?>">Events</a> | 
<a href="index.php?time=suppress&auth=<?=$_REQUEST['auth']?>">Suppress Alerts</a> | 
<a href="index.php?auth=<?=$_REQUEST['auth']?>">10 Minutes</a> | 
<a href="index.php?time=hour&auth=<?=$_REQUEST['auth']?>">Hour</a> | 
<a href="index.php?time=half&auth=<?=$_REQUEST['auth']?>">12 Hours</a> | 
<a href="index.php?time=day&auth=<?=$_REQUEST['auth']?>">24 Hours</a>
<?php 
	if ($week == 1) {
?>
| <a href="index.php?time=week&auth=<?=$_REQUEST['auth']?>">Last Week</a>
<?php 
	}
	if ($is_admin == 1) {
?>
| <a href="index.php?admin=1&auth=<?=$_REQUEST['auth']?>">Admin</a>
<?php
	}
?>
] 
<?php
	# if someone has viewed the images on the web, mark them as being
	# notified.  
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("select max(eventId) from images");
	$stmt->execute();
	$stmt->bind_result($eventId);
        $stmt->fetch();
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$stmt = $conn->prepare("update images set eventId=? where eventId=0");
	$stmt->bind_param("s", $eventId);
	$stmt->execute();

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
} else if ($_REQUEST['events'] == 1) {
	$count=0;
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$query="select distinct(user),lastNotify,isHome,homeTime from users";
	$stmt = $conn->prepare($query);
	$stmt->execute();
	$stmt->bind_result($user,$lastNotify,$isHome,$homeTime);
	while($stmt->fetch()){
		$count++;
		if ($count == 1) {
			?><table width=40% border=1 cellspacing=0 cellpadding=0><tr>
			<td><center><b>User</td>
			<td><center><b>Last Push Notification</td>
			<td><center><b>Status</td></tr>
				<?php
		}
		if ($isHome > 0) {
			$status = "<font color=green>Arrived</font> at home at $homeTime";
		} else {
			$status = "<font color=red>Departed</font> home at $homeTime";
		}
                 ?><tr><td align=center><?=$user?></td><td><center><?=$lastNotify?></td><td><center><?=$status?></td></tr>
		<?php
	}
	echo "</table><br>";
	$count=0;
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	$query="select distinct(eventId) from images where 1 order by date desc";
	$stmt = $conn->prepare($query);
	$stmt->execute();
	$stmt->bind_result($eventId);
	while($stmt->fetch()){
		$count++;
		$conn2 = new mysqli($db_server, $db_username, $db_password, $db_database);
		$query2="select max(date),min(date),count(id),UNIX_TIMESTAMP(max(date)),UNIX_TIMESTAMP(min(date)),notified from images where eventId=?";
		$stmt2 = $conn2->prepare($query2);
		$stmt2->bind_param("s", $eventId);
		$stmt2->execute();
		$stmt2->bind_result($max,$min,$ecount,$unix_max,$unix_min,$notified);
		if ($count == 1) {
			?><table width=40% border=1 cellspacing=0 cellpadding=0><tr>
			<td><center><b>EventID</td>
			<td><center><b>Number of Images</td>
                        <td><center><b>Start Time</td>
			<td><center><b>Stop Time</td>
			<td><center><b>Seconds</td></tr>
				<?php
		}
		#	echo "$unix_max $unix_min ($secs)<br>";
		while($stmt2->fetch()){
			$eurl = "?event=" . $eventId . "&auth=" . $_REQUEST['auth']; 
			$secs=$unix_max-$unix_min;
			if ($notified > 0) {
				$notified="<font color=red>*</font>";
			} else {
				$notified="";
			}
                 	?><tr><td align=center><a border=0 href="<?=$eurl?>"><?=$eventId?></a><?=$notified?></td>
			  <td><center><?=$ecount?></td><td><center><?=$min?></td><td><center><?=$max?></td>
			  <td><center><?=$secs?></td></tr>
		<?php
		}
	}
} else {
	$count=0;
	$conn = new mysqli($db_server, $db_username, $db_password, $db_database);
	if ($_REQUEST['event']) {
		$query="select image,date,notified from images where eventId=? order by date desc";
		$stmt = $conn->prepare($query);
		$stmt->bind_param("s", $_REQUEST['event']);
	} else {
		$query="select image,date,notified from images where DATE_SUB(NOW(),INTERVAL $last) <= date order by date desc";
		$stmt = $conn->prepare($query);
	}
	$stmt->execute();
	$stmt->bind_result($image,$date,$notified);
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
				if ($notified > 0) {
					echo "<font color=red>*</font>";
				}
				echo "</td>";
			} else {
			?>
			<td align=center><a border=0 href="<?=$image?>"><img width=100% src="<?=$image?>"></a><br>

			<?php  
				#echo date('l m/d/y g:i:s A ', strtotime($key));
				#echo date('l m/d/y g:i:s A ', $key);
				echo $date;
				if ($notified > 0) {
					echo "<font color=red>*</font>";
				}
				echo "</td></tr>";
			}
			#print "$key\n";
	}
	?></table><?php
}

	 
?>

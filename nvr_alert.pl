#!/usr/bin/perl

# IPCamViewer 
# https://github.com/bdwilson/IPCamViewer
#

use File::Basename;
use File::Find;
use File::stat;
use Date::Manip;
#use Time::localtime;
use LWP::UserAgent;
use Astro::Sunrise;
use Data::Dumper;
use Config::Simple;
use DBI;

# Location of your config file.
$config = "/etc/nvr_alert.cfg";
# Assuming all images uploaded are .jpg files
$extensions =  qw'\.jpg';

######### Shouldn't need to go down here ####
if (!-f "$config") {
	print "Please create a config file from the sample and put it here: $config\n";
	exit;
}

$c = new Config::Simple($config);
$lat=$c->param('options.lat');
$long=$c->param('options.long');
$loc=$c->param('options.ftpdir');
$false_mins=$c->param('options.false_mins');
$days_to_keep=$c->param('options.days_to_keep');
$url=$c->param('options.url');
$logfile=$c->param('options.log');
$max_notify_interval=$c->param('options.max_notify_interval');
$geo_ignore_interval=$c->param('geohopper.geo_ignore_interval');
$geo_enable=$c->param('geohopper.enable');
$ignore_for_all=$c->param('geohopper.ignore_for_all');
$database=$c->param('mysql.database');
$hostname=$c->param('mysql.hostname');
$username=$c->param('mysql.username');
$password=$c->param('mysql.password');

$data_source= "DBI:mysql:database=$database;host=$hostname;port=3306";
$dbh = DBI->connect($data_source, $username, $password) ||
  print "Cannot connect to $data_source: $dbh->errstr\n";

$sta = $dbh->prepare("select * from images where 1");
$sta->execute or print $DBI::errstr;
$files=$sta->fetchall_hashref("image");

find(\&findfiles,$loc);

$sta = $dbh->prepare("select count(id) from images where eventId=0");
$sta->execute or print $DBI::errstr;
$count=$sta->fetchrow;

if ($count>0) {
	# if we have images, load up the users who qualify for alerts
	if ($max_notify_interval > 0) {
		$q="select * from users where DATE_SUB(NOW(),INTERVAL $max_notify_interval MINUTE) > lastNotify";
	} else {
		$q="select * from users where 1";
	}
	$sta = $dbh->prepare($q);
	$sta->execute or print $DBI::errstr;
	$users=$sta->fetchall_hashref("user");
	$size = keys %$users;
}

if ($size>0) {
	# if we have users to notify...
	$date = &ParseDate("now");
	#$hour = &UnixDate($date,"%H"); 
	$sunrise = sun_rise($long,$lat);
	$sunset = sun_set($long,$lat);
	$sunrise_d= ParseDate($sunrise);
	$sunset_d= ParseDate($sunset);
	$delta = DateCalc($sunrise_d,$date);
	$minutes_sr= Delta_Format($delta,1,"%mt");
	$delta = DateCalc($sunset_d,$date);
	$minutes_ss= Delta_Format($delta,1,"%mt");

	# Check sunrise/sunset offsets
	$skip=0;
	$notified=0;
	&checkFalseAlarm($minutes_sr);
	&checkFalseAlarm($minutes_ss);

	# Find out if any users are home
	$isHome=0;
	$homeCount=0;
	foreach $user (keys %$users) {
		if ($users->{$user}{'isHome'}) {
			$isHome=1;
			$homeCount++;
		}
	}

	# Do geoLocation based suppression for ALL users if 1 user just
	# left/arrived within $geo_ignore_interval. Requires Geohopper.
	if ($ignore_for_all && $geo_enable) {
		$sta = $dbh->prepare("select count(user) from users where DATE_SUB(NOW(),INTERVAL $geo_ignore_interval MINUTE) < homeTime");
		$sta->execute or print $DBI::errstr;
		$igcount=$sta->fetchrow;
		if ($igcount>0) {
			$skip=1;
			$ignore="geoLocation ignore $igcount users arrived/left within $geo_ignore_interval mins";
		}
	}

	# Process per-camera ignore/suppression times
        $stb=$dbh->prepare("select distinct(location) from images where eventId=0");
        $stb->execute or print $DBI::errstr;
	while($location = $stb->fetchrow_array) {
		$locations{$location}++; 
		$stc=$dbh->prepare("select ignore_ranges,ignoreHome,ignoreAway from cameras where location=?");
		$stc->execute($location) or print $DBI::errstr;
		$ignoreHome="";
		$ignoreAway="";
		while(my $row = $stc->fetchrow_arrayref) {
			$ignoreHome=$row->[1];
			$ignoreAway=$row->[2];
			my (@ranges)=split(/\,/,$row->[0]);
			foreach my $t_slice (@ranges) {
				my ($s,$e)=split(/\-/,$t_slice);
				my $ret = &checkRange($s,$e);
				if ($ret eq 1) {
					$locations{$location}--;
					$ignore="found ignore range $t_slice for $location";
					&log("Found ignore time range $t_slice for $location, ignoring the event");
				}
			}
		}
		# process cameras set to ignore if at least 1 user is home
		if ($ignoreHome && $isHome) {
			$locations{$location}--;
			$ignore="found ignore if home for $location";
			&log("Found ignore when 1 user is at home for $location, ignoring the event");
		}
		# process cameras set to ignore if nobody is at home
		if ($ignoreAway && $isHome == 0) {
			$locations{$location}--;
			$ignore="found ignore if away for $location";
			&log("Found ignore when nobody is at home for $location, ignoring the event");
		}
			
	}

	# Process per-camera PIR input data, but only after other suppression
	# methods have been processed
	foreach $location (keys %locations) {
		$sta=$dbh->prepare("select count(pirName) from cameras where pirName!='' and location=?");
     		$sta->execute($location) or print $DBI::errstr;
                $igcount=$sta->fetchrow;
		&log("Checking $location for a PIR: $igcount");

		# if we have a defined PIR for the camera, process triggers;
		# suppress anything outside of 3 minutes from a PIR trigger. 
		if ($igcount > 0) {
			$stc=$dbh->prepare("select pirName,pirTime from cameras where location=? and ((DATE_SUB(NOW(),INTERVAL 3 MINUTE) < pirTime) AND (DATE_ADD(NOW(),INTERVAL 3 MINUTE) > pirTime))");
     			$stc->execute($location) or print $DBI::errstr;
			$ignorePIR=1;
               		while(my $row = $stc->fetchrow_arrayref) {
				# we have a row, which means PIR was triggered 
				$ignorePIR=0;
				&log("Found a PIR event for $location ($row->[0] - $row->[1]), NOT ignoring the event");
			}
			if ($ignorePIR) {
				&log("Didn't find a PIR event for $location, ignoring the event");
				$locations{$location}--;	
			}
		}
	}
			

	# are there any locations left to process after going through
	# ignore/suppression routines
	$loc_count=0;
	foreach $location (keys %locations) {
		if ($locations{$location} eq 1) {
			$loc_string .= join(", ",$location) . ", ";
			$loc_count++;
		}
	}

	chop($loc_string);
	chop($loc_string);

	$sta = $dbh->prepare("select max(eventId) from images");
	$sta->execute or print $DBI::errstr;
	$eventId=$sta->fetchrow;
	$eventId=$eventId+1;

	# if we have locations with alerts and no alerts skipped..
	if (!$skip && $loc_count > 0) {
		$sta = $dbh->prepare("delete from suppress where expiration < NOW()");
		$sta->execute or print $DBI::errstr;
		$sta = $dbh->prepare("select authkey from suppress where expiration >= NOW()");
		$sta->execute or print $DBI::errstr;
		while ($row=$sta->fetchrow_arrayref) {
			$suppress{$row->[0]}++;
		}

		# do per-User geoLocation blocking if configured; person who
		# didn't enter/exit still gets notified.
		if (!$ignore_for_all && $geo_enable) {
			$sta = $dbh->prepare("select authkey from users where DATE_SUB(NOW(),INTERVAL $geo_ignore_interval MINUTE) < homeTime");
			$sta->execute or print $DBI::errstr;
			while ($row=$sta->fetchrow_arrayref) {
				$suppress{$row->[0]}++;
			}
		}

		# process each available user
		foreach $user (keys %$users) {
			if (!$suppress{$users->{$user}{'authkey'}} && $users->{$user}{'enabled'}) {
				$link = "$url?event=$eventId&auth=$users->{$user}{'authkey'}";
				$sta = $dbh->prepare("update users set lastNotify=NOW() where user=?");
				$sta->execute($user) or print $DBI::errstr;
				&log("$count video event(s) for $loc_string the past few minutes, $homeCount user(s) at home, please review alerts. $link");
				&pushover($users->{$user}{'pushoverApp'},$users->{$user}{'pushoverKey'},"$loc_string","$count video event(s) for $loc_string the past few minutes, $homeCount user(s) at home, please review alerts.","$link");
				$notified++;
			}
		}
	} else {
		&log("DEBUG: I suppressed an alert $minutes_sr $minutes_ss ($ignore) $homeCount users at home. ");
	}
	$sta = $dbh->prepare("update images set notified=?,eventId=? where eventId=0");
	$sta->execute($notified,$eventId) or print $DBI::errstr;
	&doExpire();
}

sub doExpire {
        if ($days_to_keep > 0)  {
                $sta = $dbh->prepare("select id,image from images where DATE_SUB(NOW(),INTERVAL $days_to_keep DAY) > date");
                $sta->execute or print $DBI::errstr;
                while ($row=$sta->fetchrow_arrayref) {
                        if (-w "$loc/$row->[1]") {
                                system("rm -f \"$loc/$row->[1]\"");
                                $stb = $dbh->prepare("delete from images where id=?");
                                $stb->execute($row->[0]) or print $DBI::errstr;
                        }
                }
        }
}

sub checkRange {
	my ($s,$e) = @_;
	(@start)=split(/:/,$s);
	(@end)=split(/:/,$e);
	
	if ($start[0] < 10) {
		$start[0]='0'. $start[0];
	}
	if ($end[0] < 10) {
		$end[0]='0' . $end[0];
	}
	if ($end[0] < $start[0]) {
		$end_date = ParseDate("tomorrow");
		$end_date= &UnixDate($end_date,"%m/%d/%y"); 
	} else {
		$end_date = &UnixDate($date,"%m/%d/%y"); 
	}
	$start_date = &UnixDate($date,"%m/%d/%y"); 

	$start_date .= " $start[0]";
	$end_date .= " $end[0]";
	if ($start[1]) {
		$start_date .= ":$start[1]:00";
	} else {
		$start_date .= ":00:00";
	}
	if ($end[1]) {
		$end_date .= ":$end[1]:00";
	} else {
		$end_date .= ":00:00";
	}
	$start_date=ParseDate($start_date);
	$end_date=ParseDate($end_date);
	$sret=Date_Cmp($start_date,$date);
	$eret=Date_Cmp($date,$end_date);
	if ($sret <=0 && $eret <=0) {
		return 1;
	} 
	return 0
}

sub checkFalseAlarm {
    my $delta = shift;
    $delta =~ s/\-//; 
    if ($delta <= $false_mins) {
	$skip=1;
    }
}

sub pushover {
	my $app=shift;
	my $user=shift;
	my $loc=shift;
	my $msg=shift;
	my $link=shift;

	use LWP::UserAgent;

	LWP::UserAgent->new()->post(
  	"https://api.pushover.net/1/messages.json", [
  	"token" => "$app",
  	"user" => "$user",
        "title" => "Video events for $loc",
  	"message" => "$msg",
        "url" => "$link",
]);
}

sub findfiles {
  my $full = $File::Find::name;
  my $file = $_;
  my $dir = dirname($full);
	
  return unless $full =~ m/$extensions/io;
  my $subdir = $dir;
  $subdir =~ s/$loc\///g;
  $basefile=$subdir . "/$file";
  $subdir =~ s/\_/ /g;
  return if ($files->{$basefile});
  my $st = stat($full);
  $sta = $dbh->prepare("insert into images VALUES(0,?,FROM_UNIXTIME(?),?,0,0)");
  $sta->execute($basefile,$st->[9],$subdir) or print $DBI::errstr;
}

sub log {
  my $entry=shift;
  if ($logfile) {
  	open(LOG, ">>$logfile");
  	my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst)=localtime(time);
  	my $timestamp = sprintf ("%04d%02d%02d %02d:%02d:%02d",
                           $year+1900,$mon+1,$mday,$hour,$min,$sec);
  	print LOG "[$timestamp] $entry\n";
  	close(LOG);
  } 
}

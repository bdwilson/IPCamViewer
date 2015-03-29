IPCamView - IP Camera Web-based viewer/notifier
=======
<br>
This set of scripts allows users the ability to read a directory full of images
(i.e. images FTP'ed by your camera), send a notification to your mobile device
(via [Pushover](https://pushover.net/)), and allow you to view the images. This
script also allows you to specify times to exclude notifications and will
exclude notifications around sunrise/sunset times. It includes rudimentry
authentication so you can limit who can access your notifications as well as
the ability to suppress notifications for some period of time from your phone
(i.e. in case you're expecting lots of movement for a particular period). I
currently use these with my Hikvision cameras since their built-in motion
detection + built NVR capabilities in writing to SMB shares.  This allows me to
do all of my monitoring (Web server, MySQL, VSFTP, SMB) on a Raspberry Pi with
an external drive. 

Screen Shots
---------
Web Interface
![Sample shot](https://dl.dropbox.com/u/853747/Jing/2015-02-28_2237.png)

Pushover Notification (iOS)
![Pushover Notification](https://dl.dropboxusercontent.com/u/853747/Jing/IMG_6453.JPG)

Requirements
------------
- Linux system (or Raspberry Pi)
- [Pushover account](https://pushover.net/) - You will also need to [register
  an application](https://pushover.net/apps/build) and you can use my logo
(https://pushover.net/icons/TKeuSZYBwATtanz.png). There is a free trial period
available so you can see if you like the app; it's awesome and definately money
well spent.
- Apache (or webserver you know how to configure) that is Internet accessible
  (or can be opened to the Internet).
- MySQL (and PHPMyAdmin if you aren't comfortable updating/adding cameras/users
  via command-line).  
- FTP Server - This script expects each camera to store images in a
  sub-directory.
- An IP camera that supports FTP of images when motion is triggered and
  optionally a URL to display a real-time snapshot.
- Some method of calling the alert script (cronjob or via e-mail based procmail
  trigger)
- Perl (and these modules: File::Basename File::Find File::stat Date::Manip Time::localtime LWP::UserAgent Astro::Sunrise Data::Dumper DBI)
- Optional iOS App [GeoHopper](https://itunes.apple.com/us/app/geohopper/id605160102?mt=8) helps identify if the user is home or away

I recommend installing [cpanminus](https://github.com/miyagawa/cpanminus) and
installing the Perl modules that way, or you can intall them via your distro
packaging system.
<pre>
sudo apt-get install curl
curl -L http://cpanmin.us | perl - --sudo App::cpanminus
</pre>

Then install the modules..
<pre>
sudo cpanm File::Basename File::Find File::stat Date::Manip Time::localtime LWP::UserAgent Astro::Sunrise Data::Dumper DBI
</pre>

Installation
--------------------
1. Install Perl modules.
2. Configure your webserver. For Apache, place cam.conf in
/etc/apache2/sites-enabled and change the NVR location to the base location where
directories are for each camera that your FTP server is writing to and NVR_WEB
to be where you will store your PHP files.  We'll copy these over later below.
Restart Apache.
3. Create MySQL user (assuming you're using root to create the account).
<pre>
$ mysql -u root -p
Enter Password:
mysql> create database cam; 
mysql> create user cam;
mysql> grant all on cam.\* to 'cam'@'localhost' identified by 'cam';
mysql> flush PRIVILEGES;
mysql> quit
</pre>
Then install the DB schema.
<pre>
$ mysql -u cam -pcam cam < cam.sql
</pre>
Now you can see an example of my data. ignoreHome and ignoreAway should be set
to 0 unless you want to ignore/suppress events if someone is either Home or
all users are Away. If you want to suppress events for the camera, then toggle
these to 1 instead of 0. These variable rely on the GeoHopper dependancy.
<pre>
mysql> select * from cameras;
+-----+-------------+---------+---------------------------------------------------------------------+-----------------+------------+------------+
| cid | location    | enabled | snapshot_url 							    | ignore_ranges   | ignoreHome | ignoreAway |
+-----+-------------+---------+---------------------------------------------------------------------+-----------------+------------+------------+
|   1 | Front Porch |       1 | http://user:password@192.168.2.7/Streaming/channels/1/picture       |                 |          0 |          0 |
|   2 | Garage      |       1 | http://user:password@192.168.2.2/Streaming/channels/1/picture       | 22-23,3:30-4:30 |          0 |          0 |
+-----+-------------+---------+---------------------------------------------------------------------+-----------------+------------+------------+
mysql> select * from users;
+-----+--------+-----------------+---------+-------+------+--------------------------------+--------------------------------+---------------------+--------+---------------------+
| uid | user   | authkey         | enabled | admin | week | pushoverApp                    | pushoverKey                    | lastNotify          | isHome | homeTime            |
+-----+--------+-----------------+---------+-------+------+--------------------------------+--------------------------------+---------------------+--------+---------------------+
|   1 | admin  | AAAAABBBAAAA    |       1 |     1 |    1 | pushoverAppIDHere              | pushoverAPIKeyHere             | 2015-03-17 21:24:47 |      1 | 2015-03-17 17:10:51 |
|   2 | user   | BBBBAAAABBB     |       1 |     0 |    0 | pushoverAppIDHere              | pushoverAPIKeyHere             | 2015-03-17 21:24:48 |      1 | 2015-03-17 14:55:54 |
+-----+--------+-----------------+---------+-------+------+--------------------------------+--------------------------------+---------------------+--------+---------------------+
</pre>
4. Create your users and cameras. Note the the cron script will convert
underscores (\_) to spaces for location names, so your FTP directory should be
"Front_Porch", but in the database, it should be "Front Porch". If you don't
have a snapshot URL, then you can leave it blank.  Ignore ranges can be blank
too if you don't want to suppress any notifications (other than the default
sunrise/sunset). The first column is auto-increment, so leave that empty like
below). The week column enables the use of seeing all images for a whole week.
This will kill your cellular data plan so you might not want to enable it for
everyone. User AUTHKEY should be only numbers and letters to simplify
things. It's not like you're protecting fort knox. This is also why I'm leaving
the authkey in clear text as a GET. If you don't like it, send me a git pull :)
<pre>
$ mysql -u cam -pcam
mysql> use cam;
mysql> insert into cameras VALUES("","Front Porch","1","http://user:password@192.168.2.7/Streaming/channels/1/picture","",0,0);
mysql> insert into cameras VALUES("","Back Porch","1","http://user:password@192.168.2.7/Streaming/channels/1/picture","17-18,4:30-5:30",0,0);
mysql> insert into users VALUES("","user1","8675309","1","0","YOUR_PUSHOVER_APP_ID_HERE","YOUR_PUSHOVER_API_KEY_HERE","",0,"");
mysql> insert into users VALUES("","user2","C3PO","1","1","YOUR_PUSHOVER_APP_ID_HERE","YOUR_PUSHOVER_API_KEY_HERE","",0,"");
mysql> quit
</pre>
5. Copy config.php, index.php and image.php to the NVR_WEB location you
configred in your Apache cam.conf.  Edit config.php and set the *webdir* to be
the Alias location where the images will be surfaced from (i.e. where your FTP
server is writing files).  Set *ip_net* to be a regex to match your local IP
network if you want to bypass auth from that network. If you don't want to
bypass auth on your local net, set this to be */^256.256.256.\d+$/*.
6. Edit nvr_alert.pl and modify/point to nvr_alert.cfg do the following:
   * Configure your *lat* and *long* to correctly configure sunrise/sunset
times. (maps.google.com) can help you if you look for your address then get the
lat/long from the URL.
   * Set *loc* to be the same location you configured as the NVR location in the
cam.conf for Apache.  This is the same directory that your FTP server is
writing the images.  This location should have sub-folders in it so each camera
has their own directory with images in it.
   * *false_mins* should contain the number of minutes above and below the
sunrise/sunset that you want to use as a buffer. 20 seems to work good. **Note,
even if you're not notified of these items they will still show on the web.
Also, you can uncomment and update a section of code in nvr_alert.pl if you want to
debug suppressed alerts.**
   * *days_to_keep* is how many days worth of images you wish to keep. This
will purge images from the database and file system older than X days.  Set to
0 if you don't want it to remove any files. Your user that runs this job needs
to have permissions to remove files from this location.
   * *url* is the location where your index.php file will be served up from on
the Internet.
   * *max_notify_interval* is the minimum number of minutes inbetween
nofications. This is mainly for if the job is run by an external script (such
as procmail or snmptrap). If you're running this from cron, just set this to
the same number of minutes the cronjob is run as.  
   * *geohopper* options should be self explanitory.  You need to install the
iOS app, create a Web Service item that points to your
url/geohopper.php?auth=YOURAUTHID. Next, create a location called "Home", then
link the Web Service entry to your "Home" location.  
   * database info should match config.php and the info you used to create your 
database and user from the initial steps.
7. Make sure you can get to index.php without any errors. You can always
uncomment out the first few lines of index.php to enable debugging if you are
having issues.  
8. Setup a cronjob for nvr_alert.pl to run every 5 minutes. Make sure your
*max_notify_interval* is set to 5.
<pre>
\*/5 \* \* \* \* /mnt/storage/NVR/bin/nvr_alert.pl
</pre>
Another option (which is what I do) is to setup your camera to email your
Raspberry Pi/Linux box, then process those messages with procmail and have procmail trigger nvr_alert.pl.
If you choose to go that route, you can use the nvr_procmail.sh script to call
nvr_alert.pl.  It will keep more than 1 instance from running at a time and
give 60 seconds for images from an "event" to roll in. This method also helps
when using Geohopper since it essentially gives the system 60 seconds for you
to "leave" or "arrive" at your Home before calling nvr_alert.pl, thus your
Geohopper suppression will work better.  You'll have to setup a user on your
Linux machine, configure a Mail daemon to receive mail, make sure procmail is
installed and enabled for your mailer, and then setup a .procmailrc file that
should at least have something like this:
<code>
:0
* ^From:.*nvr@raspberrypi.mydomain.*
| /storage/samba/Pictures/NVR/nvr_procmail.sh
</code>

Debugging
-----------
1. Make sure your Apache user can access the files your FTP user writes. 
2. Uncomment the debug lines in index.php if "things aren't working".  
3. Drop a .jpg file into your a *Test* sub-directory under your FTP folder and
run nvr_alert.pl by hand from the command line.
4. Directory layout should look similar to this:
   * FTP Directory
   <pre>
   /mnt/storage/NVR                 <-- Base FTP directory
                    /Camera         <-- Sub-directory for Camera 1
                    /Camera_2       <-- Sub-directory for Camera 2
   </pre>
   * Web Directory
   <pre>
   /mnt/storage/NVR_WEB                <-- Base Web Directory
                           /index.php  <-- PHP Scripts
                           /config.php 
                           /image.php  
   </pre>
5. Check your log.
6. Contact me for help (see below). It's alot harder to explain and document
software for someone else than it is to just do it for yourself, so I'm sure I
left something out.

Bugs/Contact Info
-----------------
Bug me on Twitter at [@brianwilson](http://twitter.com/brianwilson) or email me [here](http://cronological.com/comment.php?ref=bubba).

There is also a [thread at IPCamTalk](http://www.ipcamtalk.com/showthread.php?2790-Web-based-viewer-notifier-for-Hikvision-FTP-alerts) where you can find out more info/receive support.


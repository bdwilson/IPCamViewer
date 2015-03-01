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

More Info
---------
(https://dl.dropbox.com/u/853747/Jing/2015-02-28_2237.png)

Requirements
------------
- Linux system (or Raspberry Pi)
- [Pushover account](https://pushover.net/) - You will also need to [register
  an application](https://pushover.net/apps/build) and you can use my logo
(https://pushover.net/icons/TKeuSZYBwATtanz.png). 
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
3. Configure MySQL. 
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
Now you can see an example of my data.
<pre>
mysql> select * from cameras;
+-----+-------------+---------+---------------------------------------------------------------+---------------------------------------+
| cid | location    | enabled | snapshot_url                                                  | ignore_ranges                         |
+-----+-------------+---------+---------------------------------------------------------------+---------------------------------------+
|   1 | Front Porch |       1 | http://user:password@192.168.2.7/Streaming/channels/1/picture |                                       |
|   2 | Garage      |       1 | http://user:password@192.168.2.2/Streaming/channels/1/picture | 22-23,3:30-4:30,17:15-17:45,7:15-7:35 |
+-----+-------------+---------+---------------------------------------------------------------+---------------------------------------+

mysql> select * from users;
+-----+-------+--------------+---------+------+-------------------+--------------------+---------------------+
| uid | user  | authkey      | enabled | week | pushoverApp       | pushoverKey        | lastNotify          |
+-----+-------+--------------+---------+------+-------------------+--------------------+---------------------+
|   1 | admin | AAAAABBBAAAA |       1 |    1 | pushoverAppIDHere | pushoverAPIKeyHere | 2015-02-28 13:35:54 |
|   2 | user  | BBBBAAAABBB  |       1 |    0 | pushoverAppIDHere | pushoverAPIKeyHere | 2015-02-28 13:35:54 |
+-----+-------+--------------+---------+------+-------------------+--------------------+---------------------+
</pre>
4. Update users and cameras. Note the the cron script will convert underscores
to space names for location names, so your FTP directory should be
"Front_Porch", but in the database, it should be "Front Porch". If you don't
have a snapshot URL, then you can leave it blank.  Ignore ranges can be blank
too if you don't want to ignore any alerts (other than the default
sunrise/sunset). The first column is auto-increment, so leave that empty like
below). The week column enables the use of seeing all images for a whole week.
This will kill your cellular data plan so you might not want to enable it for
everyone. **User AUTHKEY should be only numbers and letters to simplify
things. It's not like you're protecting fort knox. This is also why I'm leaving
the authkey in clear text as a GET. If you don't like it, send me a git pull :)**
<pre>
$ mysql -u cam -pcam
mysql> use cam;
mysql> insert into cameras VALUES("","Front Porch","1","http://user:password@192.168.2.7/Streaming/channels/1/picture","");
mysql> insert into cameras VALUES("","Back Porch","1","http://user:password@192.168.2.7/Streaming/channels/1/picture","17-18,4:30-5:30");
mysql> insert into users VALUES("","user1","8675309","1","0","YOUR_PUSHOVER_APP_ID_HERE","YOUR_PUSHOVER_API_KEY_HERE","");
mysql> insert into users VALUES("","user2","C3PO","1","1","YOUR_PUSHOVER_APP_ID_HERE","YOUR_PUSHOVER_API_KEY_HERE","");
mysql> quit
</pre>
5. Copy config.php, index.php and image.php to the NVR_WEB location you
configred in your Apache cam.conf.  Edit config.php and set the *webdir* to be
the Alias location where the images will be surfaced from (i.e. where your FTP
server is writing files).  Set *ip_net* to be a regex to match your local IP
network if you want to bypass auth from that network. If you don't want to
bypass auth on your local net, set this to be */^256.256.256.\d+$/*.
6. Edit nvr_alert.pl and do the following:
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
Another option would be setup your camera to email your local machine, then
process those messages with procmail and have procmail trigger nvr_alert.pl.
If you choose to go that route, you can use the nvr_procmail.sh script to call
nvr_alert.pl.  It will keep more than 1 instance from running at a time and
give 30 seconds for images from an "event" to roll in.

Debugging
-----------
1. Make sure your Apache user can access the files your FTP user writes. 
2. Uncomment the debug lines in index.php if "things aren't working".  
3. Drop a .jpg file into your a *Test* sub-directory under your FTP folder and
run nvr_alert.pl by hand from the command line.
4. Directory layout should look similar to this:
   * FTP Directory
   <pre>
   /mnt/storage/NVR                <-- Base FTP directory
                   /Camera         <-- Sub-directory for Camera 1
                   /Camera_2       <-- Sub-directory for Camera 2
   </pre>
   * Web Directory
   <pre>
   /mnt/storage/NVR_WEB		   <-- Base Web Directory
                       /index.php  <-- PHP Scripts
                       /config.php 
                       /image.php  
   </pre>

Bugs/Contact Info
-----------------
Bug me on Twitter at [@brianwilson](http://twitter.com/brianwilson) or email me [here](http://cronological.com/comment.php?ref=bubba).



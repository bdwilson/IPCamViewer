#!/bin/sh
#
# - Change the path below to point to where your nvr_alert.pl script resides
# and make sure the user that anyone on the system can exectute it.
# - Make sure you mailer can accept mail as a user
# - Make sure procmail is installed an your mail server uses it (i.e. will look
# for and execute .procmailrc files
# - Create a ~/.procmailrc as your user and put something similar to this in
# there:
# :0
# * ^From:.*nvr@raspberrypi.mydomain.*
# | /storage/samba/Pictures/NVR/nvr_procmail.sh

######
# don't run more than one instance at a time
if [ ! -f /tmp/nvr_procmail ]; then
	touch /tmp/nvr_procmail
	# wait 60 seconds for alerts to roll in via FTP
	sleep 60;
	/storage/samba/Pictures/NVR/nvr_alert.pl	 
	rm -f /tmp/nvr_procmail
fi
	

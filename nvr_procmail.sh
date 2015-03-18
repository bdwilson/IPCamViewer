#!/bin/sh

# don't run more than one instance at a time
if [ ! -f /tmp/nvr_procmail ]; then
	touch /tmp/nvr_procmail
	# wait 60 seconds for alerts to roll in via FTP
	sleep 60;
	/storage/samba/Pictures/NVR/nvr_alert.pl	 
	rm -f /tmp/nvr_procmail
fi
	

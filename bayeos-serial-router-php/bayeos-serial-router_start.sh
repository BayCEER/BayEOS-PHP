#!/bin/sh
#
# Runs node.js against script, logging to a logfile. We have to do
# this because there's no way to call node directly and have start-stop-daemon
# redirect stdout to a logfile.
#
NAME=bayeos-serial-router
if [ ! -d /var/log/${NAME} ]
then mkdir /var/log/${NAME}
fi

LOGFILE=/var/log/${NAME}/run.log
ERRORFILE=/var/log/${NAME}/error.log
RUN=/usr/sbin/${NAME}.php
 
# Fork off node into the background and log to a file
${RUN} >>${LOGFILE} 2>>${ERRORFILE} </dev/null &
 
# Capture the child process PID
CHILD="$!"
 
# Kill the child process when start-stop-daemon sends us a kill signal
trap "kill $CHILD" exit INT TERM
 
# Wait for child process to exit
wait
~

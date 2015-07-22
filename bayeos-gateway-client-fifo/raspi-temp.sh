#!/bin/bash
while sleep 10 
do 
vcgencmd measure_temp | sed -e 's/[^0-9]*\([0-9.]*\).C/\1/'
done

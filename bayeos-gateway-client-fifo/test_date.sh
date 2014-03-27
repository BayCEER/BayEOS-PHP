#!/bin/sh
count=1
while(sleep 1)
do
  a=`date -u +"%Y-%m-%d %H:%M:%S"`
  count=$(($count +1))
  if [ $count -eq 10 ]
  then
  echo "$a;1;5;$count"
  echo "Test-Error" 1>&2
  count=1
  else
  echo "$a;1;;$count"
  fi
done

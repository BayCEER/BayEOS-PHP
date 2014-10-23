#!/bin/sh
count=1
while(sleep 1)
do
  count=$(($count +1))
  if [ $count -eq 10 ]
  then
      echo "Test-Error" 1>&2
	  count=1
  else
	  echo "1 5 NAN $count"
   fi
done

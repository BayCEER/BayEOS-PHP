#!/bin/sh
count=1
while(sleep 1)
do
  count=$(($count +1))
  if [ $count -eq 3 ]
  then
    echo "origin2;4;$count"
  fi
  
  if [ $count -eq 10 ]
  then
      echo "Test-Error" 1>&2
	  count=1
  else
	  echo "origin1;1;5;$count"
   fi
done

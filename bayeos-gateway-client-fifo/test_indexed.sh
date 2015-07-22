#!/bin/sh
count=1
while(sleep 1)
do
  count=$(($count +1))
  if [ $count -eq 3 ]
  then
    echo "3:4;5:$count"
  fi
  
  if [ $count -eq 10 ]
  then
      echo "Test-Error" 1>&2
	  count=1
  else
	  echo "1:1;2:1;4:5;6:$count"
   fi
done

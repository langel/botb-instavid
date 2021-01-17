#!/bin/bash

declare -a arr=(27929 15016 16620 11920 14562 24090 12449 27167 28095 39002 28156 22673 25385 38657 11999 24004)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


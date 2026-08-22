#!/bin/bash

declare -a arr=(84350 52352 74606 62472 83999 60150 86116 83870 57270 72791 20516 17525);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done

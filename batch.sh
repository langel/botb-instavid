#!/bin/bash

declare -a arr=(26623 12447 65444 64567);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done


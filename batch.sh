#!/bin/bash

declare -a arr=(28076 32779 33260 28109 33538)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


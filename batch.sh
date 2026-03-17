#!/bin/bash

declare -a arr=(12199 79127 14272 82431 82339 82348 81910);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done

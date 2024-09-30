#!/bin/bash

declare -a arr=(25375 25585 70153 70277 69455 68761 28111 13528 42986);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done


#!/bin/bash

declare -a arr=(84016 26705 84076 84148 27948 77360 12190 84312 33659 28897 25390 79754 81814 55459);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done

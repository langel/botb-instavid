#!/bin/bash

declare -a arr=(62384 62337 62243 62325 62180 259 61544 61025 61622 61427 30081 60541 51739 61622 3692 57202);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done


#!/bin/bash

declare -a arr=(8739 19780 20385 19082 13763 12356 8606 16527 8542)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done

remove some shit

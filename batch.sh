#!/bin/bash

#declare -a arr=(8739 19780 20385 19082 13763 12356 8606 16527 8542)
#declare -a arr=(12097 175 1207 16433 16607)
declare -a arr=(20500 20483 20477)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done

remove some shit

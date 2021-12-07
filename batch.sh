#!/bin/bash

declare -a arr=(15031 15885 22143 25671 28832 36255 39708 40862 46692)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


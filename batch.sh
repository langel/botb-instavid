#!/bin/bash

declare -a arr=(7096 13840 128 12384)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


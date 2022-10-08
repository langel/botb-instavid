#!/bin/bash

declare -a arr=(52072 52401 52420 52309 52244 52071 52267 52086 52255 52374)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


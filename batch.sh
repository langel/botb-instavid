#!/bin/bash

declare -a arr=(81856 81197 81188 82010 79415 20869 26552 54431 42946 58091 16077 73883 28585);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done

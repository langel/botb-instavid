#!/bin/bash

declare -a arr=(13803 67288 70220 25376 69912 70239 68167 25401 69437);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done


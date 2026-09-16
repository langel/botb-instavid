#!/bin/bash

declare -a arr=(12190 40786 14382 84321 31785 48906 75750 30448 43143);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done

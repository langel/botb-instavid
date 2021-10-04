#!/bin/bash

declare -a arr=(47914 26866 28631 4571 47667 43638 15013 47320 46920 6532 4615745714)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


#!/bin/bash

declare -a arr=(39724 67450 79576 80744 79822 72536 27472 77297 72852 75622 84347);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done

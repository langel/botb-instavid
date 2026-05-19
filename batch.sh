#!/bin/bash

declare -a arr=(63320 28164 30523 77123 69398 18138 11947 13011 33584);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done

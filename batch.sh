#!/bin/bash

declare -a arr=(81068 30442 34239 29645 69848 64657 77589);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done

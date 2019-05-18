#!/bin/bash

declare -a arr=(8024 14734 7696 11227 28796 7472 7027 1657 26611)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


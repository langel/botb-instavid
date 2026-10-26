#!/bin/bash

declare -a arr=(82853 5868678119 40786 84099 26485 29583 60397 78916 16072 80371 86267 28092);
for i in "${arr[@]}"
do
   ./instavid.sh $i
done

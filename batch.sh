#!/bin/bash

declare -a arr=(53 54 55 56 57 58 59 60 61 62 63 64 65 66 67 68 69 70 71 72 73 74 75 76 77 78 79 80 81 82 83)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


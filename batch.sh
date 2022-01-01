#!/bin/bash

<<<<<<< Updated upstream
declare -a arr=(15031 15885 22143 25671 28832 36255 39708 40862 46692)
=======
declare -a arr=(62 63 64 68 40994 41333 42128 43668 44236 47624 47900 47919 17532 28696 17659 30428 20963 8738 36198)
>>>>>>> Stashed changes

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


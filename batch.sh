#!/bin/bash

declare -a arr=(10637 10737 10359 10774 10653 10777 10756 10674 10640 10595 10283 10707 10388 10670 10655 10433 10618 10684 10610 10641 10334 10753 10609 10611 10327 10775 10405 10454 10289 10495 10675 10319 10312 10770 10761 10356 10569 10287 10335 10598 10521 10537 10772 10691 10280 10636 10406 10754 10717 10779 10759 10333 10285 10627 10749)

for i in "${arr[@]}"
do
   ./instavid.sh $i
done


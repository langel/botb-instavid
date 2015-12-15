#!/bin/bash

mkdir assets
curl --data "entry_id=13763" http://battleofthebits.org/api/instavid/track_info/ > assets/data.json

php -f assets_get.php 
./assets_resize.sh
php -f assets_create.php

cp assets/background.png ~/botb/styles/img/bg.png

# ffmpeg -loop 1 -f image2 -i b-knox%03d.png -i output.mp3 -shortest -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -t 15 -r 30000/1001 out.mp4

# cp out.mp4 ~/botb/styles/img/ 

# rm -rf assets

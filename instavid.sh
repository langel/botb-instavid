#!/bin/bash

mkdir assets
curl --data "entry_id=13763" http://battleofthebits.org/api/instavid/track_info/ > assets/data.json

php -f assets_get.php 
./assets_resize.sh
php -f assets_create.php

# cp assets/background.png ~/botb/styles/img/bg.png

echo "rendering video"
echo

ffmpeg -i assets/background.png -i assets/avatar500-%03.png -i assets/battle-art300-%03d.png -i assets/format-icon96-%03d.png -i assets/mp3file -filter_complex "
nullsrc=size=1920x1080 [base];
[0:v] setpts=PTS-STARTPTS [bg];
[1:v] setpts=PTS-STARTPTS [avatar];
[2:v] setpts=PTS-STARTPTS [battle];
[3:v] setpts=PTS-STARTPTS [format];
[base][bg] overlay=shortest=1 [tmp1];
[tmp1][avatar] overlay=shortest=1:x=1312:y=108 [tmp2];
[tmp2][battle] overlay=shortest=1:x=108:y=408 [tmp3];
[tmp3][format] overlay=shortest=1:x=462:y=462
" -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -t 15 -r 30000/1001 out.mp4

cp out.mp4 ~/derpdeck/ 

# ffmpeg -loop 1 -f image2 -i b-knox%03d.png -i output.mp3 -shortest -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -t 15 -r 30000/1001 out.mp4

# cp out.mp4 ~/botb/styles/img/ 

# rm -rf assets

#!/bin/bash

entry_id=$1

mkdir assets
curl --data "entry_id=$entry_id" http://battleofthebits.org/api/instavid/track_info/ > assets/data.json

php -f assets_get.php 
./assets_resize.sh
php -f assets_create.php

length=`mp3info -p "%S" assets/mp3`
# cp assets/background.png ~/botb/styles/img/bg.png

echo -e "rendering video\n"

ffmpeg -i assets/background.png -loop 1 -i assets/avatar500-%03d.png -i assets/battle-art300-%03d.png -i assets/format-icon96-%03d.png -i assets/botblogo-%01d.png -i assets/title.png -i assets/format-title.png -i assets/battle-time.png -i assets/mp3 -filter_complex "
nullsrc=size=1920x1080 [base];
[0:v] setpts=PTS-STARTPTS [bg];
[1:v] setpts=PTS-STARTPTS [avatar];
[2:v] setpts=PTS-STARTPTS [battle];
[3:v] setpts=PTS-STARTPTS [format];
[4:v] setpts=PTS-STARTPTS [botblogo];
[5:v] setpts=PTS-STARTPTS [title];
[6:v] setpts=PTS-STARTPTS [format_title];
[7:v] setpts=PTS-STARTPTS [battle_time];
[base][bg] overlay [tmp1];
[tmp1][avatar] overlay=1:x=1312:y=108 [tmp2];
[tmp2][battle] overlay=1:x=108:y=408 [tmp3];
[tmp3][format] overlay=1:x=462:y=462 [tmp4];
[tmp4][botblogo] overlay=1:x=108:y=780 [tmp5];
[tmp5][title] overlay=1:x=108:y=108 [tmp6];
[tmp6][format_title] overlay=1:x=570:y=480 [tmp7];
[tmp7][battle_time] overlay=1:x=462:y=608
" -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -r 30000/1001 -t "${length}" "${entry_id}.mp4"

# -t 15 == limit vid to 15 seconds

echo -e "moving output to derpdeck\n"
mv "$entry_id.mp4" ~/derpdeck/botb-instavid/

echo -e "cleaning up mess\n"
//rm -rf assets

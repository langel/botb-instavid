#!/bin/bash

entry_id=$1

mkdir assets
curl --data "entry_id=$entry_id" http://battleofthebits.org/api/instavid/track_info/ > assets/data.json

php -f assets_get.php 
./assets_resize.sh

avatar_frames=`identify assets/avatar | sed -n 'p;$=' | tail -1`;
if [ "$avatar_frames" -gt 1 ] 
then
	loop='-ignore_loop 0'
	echo -e "AVATAR :: $avatar_frames animated GIF frames detected\n"
else
	loop=''
	echo -e "AVATAR :: still image\n"
fi

length=`ffprobe assets/mp3 2>&1 | grep Duration|awk '{print $2}' | tr -d , | awk -F: '{ print ($1 * 3600) + ($2 * 60) + $3 }'`
echo -e "media length :: $length seconds\n"

php -f assets_create.php $length

echo -e "rendering video\n"
ffmpeg -i assets/background.png $loop -i assets/avatar500 -i assets/battle-art300-%03d.png -i assets/format.png -loop 1 -r 15 -i assets/botblogo-%01d.png -i assets/title.png -i assets/battle-time.png -i assets/mp3 -filter_complex "
nullsrc=size=1920x1080 [base];
[0:v] setpts=PTS-STARTPTS [bg];
[1:v] setpts=PTS-STARTPTS [avatar];
[2:v] setpts=PTS-STARTPTS [battle];
[3:v] setpts=PTS-STARTPTS [format];
[4:v] setpts=PTS-STARTPTS [botblogo];
[5:v] setpts=PTS-STARTPTS [title];
[6:v] setpts=PTS-STARTPTS [battle_time];
[base][bg] overlay=y=-'((t+0.001)/$length)*(h-H)' [tmp1];
[tmp1][avatar] overlay=1:x=108:y=108 [tmp2];
[tmp2][battle] overlay=1:x=1512:y=408 [tmp3];
[tmp3][format] overlay=1:x='716+(688-overlay_w)*0.5':y=512 [tmp4];
[tmp4][botblogo] overlay=1:x=108:y=780 [tmp5];
[tmp5][title] overlay=1:x=691:y=108 [tmp7];
[tmp7][battle_time] overlay=1:x='716+(688-overlay_w)*0.5':y=608
" -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -r 30000/1001 -t "${length}" "${entry_id}.mp4"

echo -e "moving output to derpdeck\n"
mv "$entry_id.mp4" ~/derpdeck/botb-instavid/

echo -e "cleaning up mess\n"
rm -rf assets

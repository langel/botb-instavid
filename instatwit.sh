#!/bin/bash

visual_id=$1
audio_id=$2

wget https://battleofthebits.org/player/View/$visual_id/asset.gif
wget https://battleofthebits.org/player/EntryPlay/$audio_id/asset.mp3

echo -e "resizing image\n"
convert asset.gif -coalesce asset.temp.gif
convert asset.temp.gif -filter box -resize 1080x1080 asset.large.gif
#php -f instatwit.php

avatar_frames=`identify asset.large.gif | sed -n 'p;$=' | tail -1`;
if [ "$avatar_frames" -gt 1 ] 
then
	loop='-ignore_loop 0'
	echo -e "$avatar_frames animated GIF frames detected\n"
else
	loop=''
	echo -e "still image\n"
fi


ffmpeg -i asset.mp3 $loop -i asset.large.gif -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -r 30000/1001 -t 30 $visual_id.30.mp4
ffmpeg -i out.mp4 -c copy -t 15 $visual_id.15.mp4

echo -e "cleaning up mess\n"
rm asset.*

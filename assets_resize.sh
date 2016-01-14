#!/bin/bash

echo -e "resizing avatar\n"
convert assets/avatar -filter box -resize 500x500 -coalesce assets/avatar500-%03d.png
echo -e "converting avatar\n"
ffmpeg -r 24 -i assets/avatar500-%03d.png -c:v libx264 -r 24 assets/avatar.mp4
echo -e "resizing battle_art\n"
convert assets/battle_art -resize 300x300 -coalesce assets/battle-art300-%03d.png
echo -e "resizing format_icon\n"
convert assets/format_icon -filter box -resize 64x64 -coalesce assets/format-icon96-%03d.png

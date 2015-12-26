#!/bin/bash

echo -e "resizing avatar\n"
convert assets/avatar -filter box -resize 500x500 -coalesce assets/avatar500-%03d.png
echo -e "resizing battle_art\n"
convert assets/battle_art -resize 300x300 -coalesce assets/battle-art300-%03d.png
echo -e "resizing format_icon\n"
convert assets/format_icon -filter box -resize 96x96 -coalesce assets/format-icon96-%03d.png

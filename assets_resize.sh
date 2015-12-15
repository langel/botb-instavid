#!/bin/bash

echo -e "resizing avatar\n"
convert assets/avatar -filter box -resize 500x500 -coalesce assets/avatar500_%03d
echo -e "resizing battle_art\n"
convert assets/battle_art -resize 300x300 -coalesce assets/battle_art300_%03d
echo -e "resizing format_icon\n"
convert assets/format_icon -filter box -resize 96x96 -coalesce assets/format_icon96_%03d

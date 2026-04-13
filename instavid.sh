#!/bin/bash

entry_id=$1
orientation=${2:-auto}

if [ -z "$entry_id" ]; then
  echo "usage: ./instavid.sh {entry_id} [auto|wide|vertical]"
  exit 1
fi

if [ "$orientation" != "auto" ] && [ "$orientation" != "wide" ] && [ "$orientation" != "vertical" ]; then
  echo "invalid orientation: $orientation"
  echo "expected one of: auto, wide, vertical"
  exit 1
fi

mkdir assets
curl -k https://battleofthebits.com/api/v1/entry/load/$entry_id > assets/data.json

php -f assets_get.php "$orientation"
php -f assets_create.php "$orientation"

echo -e "rendering video\n"
bash assets/ffmpeg_call

echo -e "cleaning up mess\n"
rm -rf assets

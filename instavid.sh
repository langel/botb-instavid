#!/bin/bash

entry_id=$1

mkdir assets
curl -k https://battleofthebits.org/api/v1/entry/load/$entry_id > assets/data.json

php -f assets_get.php 
php -f assets_create.php

echo -e "rendering video\n"
bash assets/ffmpeg_call

echo -e "cleaning up mess\n"
rm -rf assets

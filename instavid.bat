mkdir assets
curl -k https://battleofthebits.com/api/v1/entry/load/%1 > assets/data.json

php -f assets_get.php
php -f assets_create.php

echo -e "rendering video\n"
bash assets/ffmpeg_call

echo -e "cleaning up mess\n"
rm -rf assets

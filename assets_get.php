<?php

$data = json_decode(file_get_contents('assets/data.json'), TRUE);
print_r($data);

system('wget http://battleofthebits.org/'.str_replace(' ', '%20', $data['renderLocation']).' -O assets/mp3');
system('wget '.$data['botbr_data']['avatar_url'].' -O assets/avatar');
system('wget "http://battleofthebits.org'.$data['battle_data']['coverArtURL'].'" -O assets/battle_art');
system('wget http://battleofthebits.org'.$data['format_icon_url'].' -O assets/format_icon');
system('wget http://battleofthebits.org/disk/debris/botb_bg.png -O assets/botb_bg.png');

/*
[base][bg] overlay=y=-'($length/t)*(h-H)*0.01' [tmp1];
[tmp1][avatar] overlay=1:x=1312:y=108 [tmp2];
[tmp2][battle] overlay=1:x=108:y=408 [tmp3];
[tmp3][format] overlay=1:x=462:y=462 [tmp4];
[tmp4][botblogo] overlay=1:x=108:y=780 [tmp5];
[tmp5][title] overlay=1:x=108:y=108 [tmp6];
[tmp6][format_title] overlay=1:x=570:y=480 [tmp7];
[tmp7][battle_time] overlay=1:x=462:y=608
*/

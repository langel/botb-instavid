<?php

$data = json_decode(file_get_contents('assets/data.json'), TRUE);
print_r($data);

system('wget http://battleofthebits.org'.$data['location_url']);
system('wget '.$data['botbr_data']['avatar_url'].' -O assets/avatar');
system('wget "http://battleofthebits.org'.$data['battle_data']['coverArtURL'].'" -O assets/battle_art');
system('wget http://battleofthebits.org'.$data['format_icon_url'].' -O assets/format_icon');
system('wget http://battleofthebits.org/disk/debris/botb_bg.png -O assets/botb_bg.png');



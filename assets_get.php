<?php

$data = json_decode(file_get_contents('assets/data.json'), TRUE);
print_r($data);

#system('wget "http://battleofthebits.org/'.str_replace(' ', '%20', $data['renderLocation']).'" -O assets/mp3');
system('wget "http://battleofthebits.org/player/EntryPlay/'.$data['id'].'" -O assets/mp3');
system('wget '.$data['battle_data']['cover_art_url'].' -O assets/battle_art');
system('wget '.$data['format_icon_url'].' -O assets/format_icon');
system('wget http://battleofthebits.org/disk/debris/botb_bg.png -O assets/botb_bg.png');

// XXX need collabdong handling here
system('wget http://battleofthebits.org/'.$data['botbr_data']['avatar_from_time'].' -O assets/avatar');

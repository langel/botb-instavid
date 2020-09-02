<?php

$data = json_decode(file_get_contents('assets/data.json'), TRUE);
print_r($data);


print "\nHANDLING BATTLE COVER ART ::\n";
system('wget '.$data['battle']['cover_art_url'].' -O assets/battle_art');
print "scanning image...";
system('convert assets/battle_art -coalesce assets/battle_art_temp');
$dimensions = system("identify -format '%wx%h' assets/battle_art_temp[0]");
print "resizing from $dimensions to 450x450\n";
system('convert -size '.$dimensions.' assets/battle_art_temp -filter box -resize 450x450 assets/battle-art450');
// check if its animated
$battle_art_cli = '';
$battle_art_frames = exec("identify assets/battle-art450 | sed -n 'p;$=' | tail -1");
if ((int)$battle_art_frames > 1) {
	print "BATTLE ART :: $battle_art_frames animated GIF frames detected\n";
	$battle_art_cli .= '-ignore_loop 0 ';
}
else print "BATTLE ART :: still image \n";


print "\nHANDLING FORMAT ICON ::\n";
system('wget '.$data['format']['icon_url'].' -O assets/format_icon');
system('convert assets/format_icon -filter box -resize 64x64 -coalesce assets/format_icon.png');


print "\nHANDLING AVATARS ::\n";
$author_count = count($data['authors']);
$avatar_resize_to = ($author_count > 1) ? 250 : 500;
$assets_cli = $setpts = $position = '';
$i = 0;
foreach ($data['authors'] as $author) {
	$id = $author['id'];
	$source_file = 'assets/avatar'.$id;
	// get the image
	$avatar_string = $author['avatar_from_time'];
	// XXX wait! shouldn't the api populate this??!?!
	if ($avatar_string == '') {
		system('wget http://battleofthebits.org/disk/debris/n00b.png -O '.$source_file);
	}
	else {
		system('wget http://battleofthebits.org/disk/avatars/'.$avatar_string.' -O '.$source_file);
	}
	// resize
	$temp_file = "assets/avatarTEMP".$author['avatar_from_time'];
	print "coalescing avatar to $temp_file\n";
	system('convert '.$source_file.' -coalesce '.$temp_file);
	$target_file = "assets/avatar".$author['avatar_from_time'];
	print "resizing avatar to $target_file\n";
	system('convert '.$temp_file.' -filter box -resize '.$avatar_resize_to.'x'.$avatar_resize_to.' '.$target_file);
	// check if its animated
	$avatar_frames = exec("identify $target_file | sed -n 'p;$=' | tail -1");
	if ((int)$avatar_frames > 1) {
		print "AVATAR :: $avatar_frames animated GIF frames detected\n";
		$assets_cli .= '-ignore_loop 0 ';
	}
	else print "AVATAR :: still image \n";
	$assets_cli .= '-i '.$target_file.' ';
	// create all the other ffmpeg crap
	$setpts .= '['.($i + 6).':v] setpts=PTS-STARTPTS [avatar'.$id.'];'."\n";
	$x = $y = 108;
	if ($i % 2) $x += 250;
	if ($author_count == 2 && $i == 1) $y = 108 + 250;
	else $y += floor($i / 2) * 250;
	$position .= " [ava0".$i."];\n[ava0".$i."][avatar".$id."] overlay=1:x=".$x.":y=".$y;
	$i++;
}

print "\nDONLOAD TEH MP3 ::\n";
system('wget "http://battleofthebits.org/player/EntryPlay/'.$data['id'].'" -O assets/mp3');
$mp3_length = system("ffprobe assets/mp3 2>&1 | grep Duration|awk '{print $2}' | tr -d , | awk -F: '{ print ($1 * 3600) + ($2 * 60) + $3 }'");
echo "media length :: $mp3_length seconds\n";

$battle_offset = ($author_count < 6) ? 960 : 1020;
/*
# WORKING FFMPEG CALL 
ffmpeg -i assets/background.png -i assets/battle-art300-%03d.png -i assets/format.png -loop 1 -r 15 -i assets/botblogo-%01d.png -i assets/title.png -i assets/battle-time.png $loop -i assets/avatar500 -i assets/mp3 -filter_complex "
nullsrc=size=1920x1080 [base];
[0:v] setpts=PTS-STARTPTS [bg];
[1:v] setpts=PTS-STARTPTS [battle];
[2:v] setpts=PTS-STARTPTS [format];
[3:v] setpts=PTS-STARTPTS [botblogo];
[4:v] setpts=PTS-STARTPTS [title];
[5:v] setpts=PTS-STARTPTS [battle_time];
[6:v] setpts=PTS-STARTPTS [avatar00];
[base][bg] overlay=y=-'((t+0.001)/$length)*(h-H)' [tmp1];
[tmp1][battle] overlay=1:x=1362:y=522 [tmp2];
[tmp2][format] overlay=1:x='960-(overlay_w-50)*0.5':y=562 [tmp3];
[tmp3][botblogo] overlay=1:x=100:y=830 [tmp4];
[tmp4][title] overlay=1:x=691:y=108 [tmp5];
[tmp5][battle_time] overlay=1:x='960-(overlay_w+100)*0.5':y=666 [ava00];
[ava00][avatar00] overlay=1:x=108:y=108
" -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -r 30000/1001 -t "${length}" "${entry_id}.mp4"
*/

$ffmpeg_call = 'ffmpeg -i assets/background.png '.$battle_art_cli.'-i assets/battle-art450 -i assets/format.png -loop 1 -r 15 -i assets/botblogo-%01d.png -i assets/title.png -i assets/battle-time.png '.$assets_cli.' -i assets/mp3 -filter_complex "
nullsrc=size=1920x1080 [base];
[0:v] setpts=PTS-STARTPTS [bg];
[1:v] setpts=PTS-STARTPTS [battle];
[2:v] setpts=PTS-STARTPTS [format];
[3:v] setpts=PTS-STARTPTS [botblogo];
[4:v] setpts=PTS-STARTPTS [title];
[5:v] setpts=PTS-STARTPTS [battle_time];
'.$setpts."[base][bg] overlay=y=-'((t+0.001)/".$mp3_length.")*(h-H)' [tmp1];
[tmp1][battle] overlay=1:x=1362:y=522 [tmp2];
[tmp2][format] overlay=1:x='960-(overlay_w-50)*0.5':y=562 [tmp3];
[tmp3][botblogo] overlay=1:x=100:y=830 [tmp4];
[tmp4][title] overlay=1:x=691:y=108 [tmp5];
[tmp5][battle_time] overlay=1:x='".$battle_offset."-(overlay_w+100)*0.5':y=666".$position.'
" -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -r 30000/1001 -t '.$mp3_length.' '.$data['id'].'.mp4';

print $ffmpeg_call;

file_put_contents('assets/ffmpeg_call', $ffmpeg_call);

<?php

if (count($argv) < 3) {
	echo "give it a botb release url and corresponding battle_id -- it'll try to find entry_id's and spew them onscreen\n";
	die();
}


$page = file_get_contents($argv[1]);
$battle_id = $argv[2];

$url = 'https://battleofthebits.org/api/v1/battle/load/'.$battle_id;
system('curl -k '.$url.' > battledata.json', $ass);
$battle = json_decode(file_get_contents('battledata.json'), true);
unlink('battledata.json');


$title = substr($page, strpos($page, '"trackTitle">') + 13);
$title = substr($title, 0, strpos($title, '</h2>'));
$title = trim($title);

echo "$battle_id : $title \n";

$track_count = substr_count($page, "track-title");

echo "$track_count tracks found \n\n";

$track = $page;
$tracks = [];
$track_ids = [];
$tracksout = '';
for ($i = 0; $i < $track_count; $i++) {
	$page = substr($page, strpos($page, "track-title") + 13);
	$page = substr($page, strpos($page, "-") + 2);
	$track = html_entity_decode(substr($page, 0, strpos($page, "</")), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
	echo "$track\n";
	$url = 'https://battleofthebits.org/api/v1/entry/list?filters=battle_id~'.$battle_id.'^title~'.urlencode($track);
	echo "$url\n";
	system('curl -k '.$url.' > trackdata.json', $ass);
	if (file_get_contents('trackdata.json') == '[]') {
		$trackdata = "FAILURE TO LOCATE :: $track\n";
		$tracks_id[] = "FAIL\n";
	}
	else {
		$trackdata = json_decode(file_get_contents('trackdata.json'), true)[0];
		$tracks_id[] = $trackdata['id'];
		$trackdata = $trackdata['id'].' '.$trackdata['authors_display'].' - '.$trackdata['title']."\n";
	}
	echo $trackdata;
	$tracksout .= $trackdata;
}
unlink('trackdata.json');



echo "\nCREATING THUMBNAIL ::\n";
mkdir('assets');
$img = imagecreatetruecolor(1920, 1080);

print_r($battle);
system('wget --no-check-certificate https://battleofthebits.org/disk/debris/botb_bg.png -O assets/botb_bg.png');
$bg_pattern = imagecreatefrompng('assets/botb_bg.png');
imagesettile($img, $bg_pattern);

print "\nHANDLING BATTLE COVER ART ::\n";
system('wget --no-check-certificate '.$battle['cover_art_url'].' -O assets/battle_art');
print "scanning image...";
system('convert assets/battle_art -coalesce assets/battle_art_temp');
$dimensions = system("identify -format '%wx%h' assets/battle_art_temp[0]");
print "resizing from $dimensions to 1080x1080\n";
system('convert -size '.$dimensions.' assets/battle_art_temp -filter hermite -resize 1080x1080 assets/battle-art1080');
$battle_art = imagecreatefromstring(file_get_contents('assets/battle-art1080'));
// convert battle art to truecolor if necessary
if (!imageistruecolor($battle_art)) {
	$temp = imagecreatetruecolor(1080, 1080);
	imagecopy($temp, $battle_art, 0, 0, 0, 0, 1080, 1080);
	imagedestroy($battle_art);
	$battle_art = $temp;
}

// build colors from battle art
echo "\nfinding colors from battle art:\n";
$battle_temp = imagecreatetruecolor(1080, 1080);
imagecopy($battle_temp, $battle_art, 0, 0, 0, 0, 1080, 1080);
imagetruecolortopalette($battle_temp, false, 255);
$bg_color = imagecolorclosest($battle_temp, 33, 25, 40);
$bg_color = imagecolorsforindex($battle_temp, $bg_color);
echo "bg_color: {$bg_color['red']}, {$bg_color['green']}, {$bg_color['blue']}\n";
$bg_color = imagecolorallocate($img, $bg_color['red'], $bg_color['green'], $bg_color['blue']);
$fg_color = imagecolorclosest($battle_temp, 225, 245, 200);
$fg_color = imagecolorsforindex($battle_temp, $fg_color);
echo "fg_color: {$fg_color['red']}, {$fg_color['green']}, {$fg_color['blue']}\n";
$fg_color = imagecolorallocate($img, $fg_color['red'], $fg_color['green'], $fg_color['blue']);
imagedestroy($battle_temp);

// fill bg color
imagefilledrectangle($img, 0, 0, 1920, 1080, $bg_color);
// tile bg pattern
imagefilledrectangle($img, 0, 0, 1920, 1080, IMG_COLOR_TILED);

// battle art
imagecopy($img, $battle_art, 420, 0, 0, 0, 1080, 1080);

// save thumbnail
imagepng($img, "battle_{$battle['id']}_thumbnail.png");


system('rm -rf assets');



echo "\n\n";
echo $tracksout;

echo "\n";
echo implode(" ", $track_ids);
echo "\n\n";

<?php

if (count($argv) < 3) {
	echo "give it a botb release url and corresponding battle_id -- it'll try to find entry_id's and spew them onscreen\n";
	die();
}

include_once('text-tools.php');

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

/*
$track = $page;
$tracks = [];
for ($i = 0; $i < $track_count; $i++) {
	$page = substr($page, strpos($page, "track-title") + 13);
	$page = substr($page, strpos($page, "-") + 2);
	$track = html_entity_decode(substr($page, 0, strpos($page, "</")));
	echo "$track\n";
	$url = 'https://battleofthebits.org/api/v1/entry/list?filters=battle_id~'.$battle_id.'^title~'.urlencode($track);
	echo "$url\n";
	system('curl -k '.$url.' > trackdata.json', $ass);
	$trackdata = json_decode(file_get_contents('trackdata.json'), true)[0];
	echo $trackdata['id'].' '.$trackdata['authors_display'].' - '.$trackdata['title']."\n";
	$tracks[] = $trackdata;
}
unlink('trackdata.json');

$track_ids = [];
echo "\n\n";

for ($i = 0; $i < $track_count; $i++) {
	echo $tracks[$i]['id'].' '.$tracks[$i]['authors_display'].' - '.$tracks[$i]['title']."\n";
	$track_ids[] = $tracks[$i]['id'];
}

echo "\n\n";
echo implode(" ", $track_ids);
echo "\n";

*/

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
print "resizing from $dimensions to 900x900\n";
system('convert -size '.$dimensions.' assets/battle_art_temp -filter box -resize 900x900 assets/battle-art900');
$battle_art = imagecreatefromstring(file_get_contents('assets/battle-art900'));
// convert battle art to truecolor if necessary
if (!imageistruecolor($battle_art)) {
	$temp = imagecreatetruecolor(900, 900);
	imagecopy($temp, $battle_art, 0, 0, 0, 0, 900, 900);
	imagedestroy($battle_art);
	$battle_art = $temp;
}

// build colors from battle art
echo "\nfinding colors from battle art:\n";
$battle_temp = imagecreatetruecolor(900, 900);
imagecopy($battle_temp, $battle_art, 0, 0, 0, 0, 900, 900);
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
imagecopy($img, $battle_art, 90, 90, 0, 0, 900, 900);

echo "\nCREATING BotB LOGO :: \n";
$text = 'battleofthebits.org';
$font = './arial-black.ttf';
$size = 72;
$spacing = -11;
$x = 1080;
for ($i = 0; $i < strlen($text); $i++) {
	$bbox = imagettftext($img, $size, 0, $x, 990, $fg_color, $font, $text[$i]);
	$x += $spacing + ($bbox[2] - $bbox[0]);
}

$y = 90;

echo "\nCREATING TITLE TEXT :: \n";
$font = './Racing_Sans_One/RacingSansOne-Regular.ttf';
$size = 100;
$scale_max = 2.5;
//$text_img = imagecreatetruecolor(750, 900);
$text = get_wrapped_text($battle['title']);
$dim = get_text_dimensions($size, 0, $font, $text);
$scale = 750 / $dim[0];
$title_size = $size * $scale;
$y += $title_size;
imagettftext($img, $title_size, 0, 1080, $y, $fg_color, $font, $text);
$y += $dim[1] * $scale;

// format icons

// date completed
$size = 48;
$text = "originally completed on\n                                ".substr($battle['end'], 0, 10);
$dim = get_text_dimensions($size, 0, $font, $text);
imagettftext($img, $size, 0, 1080, 750, $fg_color, $font, $text);

// save thumbnail
imagepng($img, "battle_{$battle['id']}_thumbnail.png");


system('rm -rf assets');

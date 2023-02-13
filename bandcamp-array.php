<?php

if (count($argv) < 3) {
	echo "give it a botb release url and corresponding battle_id -- it'll try to find entry_id's and spew them onscreen\n";
//	die();
}
$bandcamp = false; // ripping from bandcamp 
// (false = using hard coded playlist array)
$api_url = 'https://battleofthebits.org/api/v1/';

function battle_from_id($battle_id) {
	GLOBAL $api_url;
	$url = $api_url . 'battle/load/'.$battle_id;
	system('curl -k '.$url.' > battledata.json', $ass);
	$battle = json_decode(file_get_contents('battledata.json'), true);
	unlink('battledata.json');
	return $battle;
}

$track_count = 0;
$track_ids = [];
$tracksout = '';
$tracksfail = '';
$ttllen = 0;

if ($bandcamp) {
	$page = file_get_contents($argv[1]);
	$battle_id = $argv[2];

	$battle = battle_from_id($battle_id);

	$title = substr($page, strpos($page, '"trackTitle">') + 13);
	$title = substr($title, 0, strpos($title, '</h2>'));
	$title = trim($title);

	echo "$battle_id : $title \n";

	$track_count = substr_count($page, "track-title");

	echo "$track_count tracks found \n\n";

	$track = $page;
	$tracks = [];
}
else {
	// manual mix mode
	$battle_id = 25;
	$battle = battle_from_id($battle_id);
print_r($battle);
	$tracks = [450, 438, 439, 303, 387, 296, 307, 277, 320, 425, 452, 275, 419, 344, 293, 440, 273, 401, 435, 433, 290, 418, 449, 295, 358, 422, 268, 260, 322, 261, 389, 262, 446, 284, 347, 321, 444, 259, 445];
	$track_count = count($tracks);
	$bandcamp = false;
};

for ($i = 0; $i < $track_count; $i++) {
	if ($bandcamp) {
		$page = substr($page, strpos($page, "track-title") + 13);
		$page = substr($page, strpos($page, " - ") + 3);
		$track = html_entity_decode(substr($page, 0, strpos($page, "</")), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
		echo "$track\n";
		$track = str_replace("~", "~~", $track);
		$url = $api_url . 'entry/list?filters=battle_id~'.$battle_id.'^title~'.urlencode($track);
	}
	else {
		$url = $api_url . 'entry/list?filters=id~' . $tracks[$i];
	}
	echo "$url\n";
	system('curl -k '.$url.' > trackdata.json', $ass);
	if (file_get_contents('trackdata.json') == '[]') {
		$trackdata = "FAILURE TO LOCATE :: $track\n";
		$trackfail .= $trackdata;
		//$track_ids[] = "FAIL";
	}
	else {
		$trackdata = json_decode(file_get_contents('trackdata.json'), true)[0];
		$track_ids[] = $trackdata['id'];
		$trackline = gmdate("G:i:s", round($ttllen)).' '.$trackdata['authors_display'].' - '.$trackdata['title']."\n";
		$ttllen += floatval($trackdata['length']);
		$tracksout .= $trackline;
		$trackdata = $trackline . $trackdata['id'] . ' ' . $trackdata['length'] . ' ' . gmdate("i:s", round($trackdata['length'])) . "\n\n";
	}
	echo $trackdata;
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

$notes = '';
$notes .= "\n\n";
$notes .= $tracksfail;

$notes .= "\n\noriginally released ASDASDASD September 21, 2011";
$notes .= "\nall tracks available free here:\nhttps://battleofthebits.org/arena/Battle/".$battle['id'];
$notes .= "\n";
$notes .= "\nsupport BotB on bandcamp here:";
if ($bandcamp) $notes .= "\n".$argv[1];
else $notes .= "\nbattleofthebits.bandcamp.com";
$notes .= "\nhttps://www.patreon.com/battleofthebits";

$notes .= "\n\n";
$notes .= $tracksout;

$notes .= "\n";
$notes .= implode(" ", $track_ids);
$notes .= "\n\n";

echo $notes;
file_put_contents("comp.txt", $notes);

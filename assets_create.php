<?php

# grok data

$data = json_decode(file_get_contents('assets/data.json'), TRUE);
$pal = $data['palette_data'];


# create background

echo "creating background \n\n";
function image_gradientrect($img,$x,$y,$x1,$y1,$start,$end) {
	if($x > $x1 || $y > $y1) {
		return false;
	}
	$s = array(
			hexdec(substr($start,0,2)),
			hexdec(substr($start,2,2)),
			hexdec(substr($start,4,2))
			);
	$e = array(
			hexdec(substr($end,0,2)),
			hexdec(substr($end,2,2)),
			hexdec(substr($end,4,2))
			);
	$steps = $y1 - $y;
	for($i = 0; $i < $steps; $i++) {
		$r = $s[0] - ((($s[0]-$e[0])/$steps)*$i);
		$g = $s[1] - ((($s[1]-$e[1])/$steps)*$i);
		$b = $s[2] - ((($s[2]-$e[2])/$steps)*$i);
		$color = imagecolorallocate($img,$r,$g,$b);
		imagefilledrectangle($img,$x,$y+$i,$x1,$y+$i+1,$color);
	}
	return true;
}
$imgWidth = 1920;
$imgHeight = 1920;
$img = imagecreatetruecolor($imgWidth,$imgHeight);
image_gradientrect($img,0,0,$imgWidth,$imgHeight, $pal['color1'], $pal['color2']);
$water_pattern = imagecreatefrompng('assets/botb_bg.png');
imagesettile($img, $water_pattern);
imagefilledrectangle($img, 0, 0, 1920, 1920, IMG_COLOR_TILED);
imagedestroy($water_pattern);
imagepng($img, 'assets/background.png');
imagedestroy($img);


# create BotB logo

$text = 'battleofthebits.org';
$font = './arial-black.ttf';
$size = 160;
$spacing = -20;
function create_color($hex, $img) {
	$r = hexdec(substr($hex,0,2));
	$g = hexdec(substr($hex,2,2));
	$b = hexdec(substr($hex,4,2));
	return imagecolorallocatealpha($img,$r,$g,$b,0);
}
for ($k = 0; $k < 5; $k++) {
	$img = imagecreatetruecolor(1704, 250);
	imagesavealpha($img, true);
	$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
	imagefill($img, 0, 0, $trans);
	$y = $size;
	for ($j = 0; $j < 4; $j++) {
		$color = ($k + $j) % 5 + 1;
		if ($j == 3) $color = 5;
		print $pal['color'.$color].' ';
		$color = create_color($pal['color'.$color], $img);
		$x = 0;
		for ($i = 0; $i < strlen($text); $i++) {
			$bbox = imagettftext($img, $size, 0, $x, $y, $color, $font, $text[$i]);
			$x += $spacing + ($bbox[2] - $bbox[0]);
		}
		if ($j == 0) $y+= 18;
		if ($j == 1) $y+= 11;
		if ($j == 2) $y+= 7;
		if ($j == 3) $y+= 4;
	}
	imagepng($img, 'assets/botblogo-'.$k.'.png');
	imagedestroy($img);
}

# title and n00b

$font = './merriweather_sans.ttf';
$font = './Racing_Sans_One/RacingSansOne-Regular.ttf';
$img = imagecreatetruecolor(1096,600);
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);
$color = create_color($pal['color5'], $img);
$size = 72;
$y = floor($size * 1.1);
$text = $data['title'];
$text = wordwrap($text, 22, "\n");
$bbox = imagettftext($img, $size, 0, 0, $y, $color, $font, $text); 
$color = create_color($pal['color4'], $img);
$text = '  '.$data['botbr_data']['name'];
imagettftext($img, $size, 0, 0, $bbox[3]+$y, $color, $font, $text); 
imagepng($img, 'assets/title.png');
imagedestroy($img);


# format title

$font = './Passion_One/PassionOne-Regular.ttf';
$img = imagecreatetruecolor(996,96);
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);
$color = create_color($pal['color5'], $img);
$size = 32;
$y = floor($size * 1.1);
$text = $data['format_title'];
imagettftext($img, $size, 0, 0, $y, $color, $font, $text); 
imagepng($img, 'assets/format-title.png');
imagedestroy($img);


# battle and time

$img = imagecreatetruecolor(1096,246);
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);
$color = create_color($pal['color5'], $img);
$size = 32;
$y = floor($size * 1.1);
$text = 'submitted to '.$data['battle_data']['title'];
$text = wordwrap($text, 50, "\n");
$text .= "\n".'                           on '.substr($data['datetime'], 0, 10);
imagettftext($img, $size, 0, 0, $y, $color, $font, $text); 
imagepng($img, 'assets/battle-time.png');
imagedestroy($img);

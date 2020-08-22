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
$imgHeight = 1080 + floor($argv[1] * 20);
$img = imagecreatetruecolor($imgWidth,$imgHeight);
image_gradientrect($img,0,0,$imgWidth,floor($imgHeight*0.5), $pal['color5'], $pal['color4']);
image_gradientrect($img,0,ceil($imgHeight*0.5),$imgWidth,$imgHeight, $pal['color4'], $pal['color3']);
$water_pattern = imagecreatefrompng('assets/botb_bg.png');
imagesettile($img, $water_pattern);
imagefilledrectangle($img, 0, 0, $imgWidth, $imgHeight, IMG_COLOR_TILED);
imagedestroy($water_pattern);
imagepng($img, 'assets/background.png');
imagedestroy($img);


# create BotB logo

$text = 'battleofthebits.org';
$font = './arial-black.ttf';
$size = 120;
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
	for ($j = 0; $j < 5; $j++) {
		$color = ($k + $j) % 5 + 1;
		if ($j == 4) $color = 2;
		print $pal['color'.$color].' ';
		$color = create_color($pal['color'.$color], $img);
		$x = 0;
		for ($i = 0; $i < strlen($text); $i++) {
			$bbox = imagettftext($img, $size, 0, $x, $y, $color, $font, $text[$i]);
			$x += $spacing + ($bbox[2] - $bbox[0]);
		}
		/*
		if ($j == 0) $y+= 18;
		if ($j == 1) $y+= 11;
		if ($j == 2) $y+= 7;
		if ($j == 3) $y+= 4;
		*/
		$y+=8;
	}
	imagepng($img, 'assets/botblogo-'.$k.'.png');
	imagedestroy($img);
}

# title and n00b

$font = './Racing_Sans_One/RacingSansOne-Regular.ttf';
$size = 72;
$scale_max = 2.5;

$wrap_at = 25;
$title_length = strlen($data['title']);
echo "\n\n  title length : $title_length\n";
if ($title_length > 40) $wrap_at = ceil($title_length * 0.6);
if ($title_length > 80) $wrap_at = ceil($title_length * 0.4);
if ($title_length > 120) $wrap_at = ceil($title_length * 0.3);
echo "  wrapping lines at $wrap_at characters \n";

// create title/n00b image object
$img = imagecreatetruecolor(1146,600);
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);

// figure out title size
$title_text = wordwrap($data['title'], $wrap_at, "\n\r");
$title_dim = imagettfbbox($size, 0, $font, $title_text);
$title_width = $title_dim[2];
$title_height = "\n". $size * (substr_count($title_text, "\n") + 2);
$max_width = 1920 - 716 - 108;
$max_height = 300;
$scale_x = $max_width / $title_width;
$scale_y = $max_height / $title_height;
$title_size = $size * min($scale_x, $scale_y, $scale_max);
echo "\n\ntitle font size :: $size\n\n";

// create entry title
$color = create_color($pal['color1'], $img);
$y = floor($title_size * 0.92);
$bbox = imagettftext($img, $title_size, 0, 25, $y, $color, $font, $title_text); 

// figure out botbr name size
$noob_text = '  '.$data['botbr_data']['name'];
$noob_dim = imagettfbbox($size, 0, $font, $noob_text);
$noob_width = $noob_dim[2];
$noob_left_padding = 25;
$max_width -= $noob_left_padding;
$scale_x = $max_width / $noob_width;
$noob_size = $size * $scale_x;

// create botbr name
$color = create_color($pal['color2'], $img);
imagettftext($img, $noob_size, 0, $noob_left_padding, $y*1.2 + $bbox[1] + 27, $color, $font, $noob_text); 
imagepng($img, 'assets/title.png');
imagedestroy($img);


# format title

$font = './Passion_One/PassionOne-Regular.ttf';
$size = 32;
$text = $data['format_title'];
$text_dim = imagettfbbox($size, 0, $font, $text);
$img = imagecreatetruecolor($text_dim[2]+88,96);
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);
$color = create_color($pal['color1'], $img);
$y = floor($size * 1.1);
imagettftext($img, $size, 0, 88, $y+12, $color, $font, $text); 
$icon = imagecreatefrompng('assets/format_icon.png');
imagecopymerge($img, $icon, 0, 0, 0, 0, imagesx($icon), imagesy($icon), 100);
imagedestroy($icon);
imagepng($img, 'assets/format.png');
imagedestroy($img);


# battle and time

$size = 32;
$text = 'submitted to '.$data['battle_data']['title'];
$text = wordwrap($text, 50, "\n");
$text .= "\n".'                           on '.substr($data['datetime'], 0, 10);
$text_dim = imagettfbbox($size, 0, $font, $text);
$img = imagecreatetruecolor($text_dim[2], 246);
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);
$color = create_color($pal['color1'], $img);
$y = floor($size * 1.1);
imagettftext($img, $size, 0, 0, $y, $color, $font, $text); 
imagepng($img, 'assets/battle-time.png');
imagedestroy($img);

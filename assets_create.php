<?php

$data = json_decode(file_get_contents('assets/data.json'), TRUE);
$pal = $data['palette_data'];

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

echo "creating background \n\n";
$imgWidth = 1920;
$imgHeight = 1080;
$img = imagecreatetruecolor($imgWidth,$imgHeight);
image_gradientrect($img,0,0,$imgWidth,$imgHeight, $pal['color1'], $pal['color2']);

$water_pattern = imagecreatefrompng('assets/botb_bg.png');
imagesettile($img, $water_pattern);
imagefilledrectangle($img, 0, 0, 1920, 1080, IMG_COLOR_TILED);
imagedestroy($water_pattern);

imagepng($img, 'assets/background.png');
imagedestroy($img);

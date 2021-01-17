<?php

# grok data

$data = json_decode(file_get_contents('assets/data.json'), TRUE);

system('curl https://battleofthebits.org/api/v1/palette/load/'.$data['botbr']['palette_id'].' > assets/pal.json');
$pal = json_decode(file_get_contents('assets/pal.json'), TRUE);
print_r($pal);

function get_wrap_at($str) {
	$wrap_at = 20;
	$str_len = strlen($str);
	echo "  wrappable string length : $str_len\n";
	if ($str_len > 40) $wrap_at = 34;
	echo "  wrapping lines at $wrap_at characters\n\n";
	return $wrap_at;
}


echo "\nCREATING BACKGROUND :: \n";
system('wget https://battleofthebits.org/disk/debris/botb_bg.png -O assets/botb_bg.png');
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
$mp3_length = system("ffprobe assets/mp3 2>&1 | grep Duration|awk '{print $2}' | tr -d , | awk -F: '{ print ($1 * 3600) + ($2 * 60) + $3 }'");
$imgHeight = 1080 + floor($mp3_length * 20);
$img = imagecreatetruecolor($imgWidth, $imgHeight);
image_gradientrect($img, 0, 0, $imgWidth,floor($imgHeight * 0.5), $pal['color5'], $pal['color4']);
image_gradientrect($img, 0, ceil($imgHeight * 0.5), $imgWidth, $imgHeight, $pal['color4'], $pal['color3']);
$water_pattern = imagecreatefrompng('assets/botb_bg.png');
imagesettile($img, $water_pattern);
imagefilledrectangle($img, 0, 0, $imgWidth, $imgHeight, IMG_COLOR_TILED);
imagedestroy($water_pattern);
imagepng($img, 'assets/background.png');
imagedestroy($img);


# create BotB logo

echo "\nCREATING BotB LOGO :: \n";
$text = 'battleofthebits.org';
$font = './arial-black.ttf';
$size = 110;
$spacing = -15;
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
		$y+=8;
	}
	imagepng($img, 'assets/botblogo-'.$k.'.png');
	imagedestroy($img);
	print "\n\n";
}



# title and n00b

echo "\nCREATING TITLE & AUTHORS TEXT :: \n";
$font = './Racing_Sans_One/RacingSansOne-Regular.ttf';
$size = 72;
$scale_max = 2.5;


// create title/n00b image object
$img = imagecreatetruecolor(1146,600);
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);

// figure out title size
$wrap_at = get_wrap_at($data['title']);
$title_text = wordwrap($data['title'], $wrap_at, "\n\r");
$title_dim = imagettfbbox($size, 0, $font, $title_text);
$title_width = $title_dim[2];
$title_height = "\n". $size * (substr_count($title_text, "\n") + 2);
$max_width = 1920 - 716 - 108;
$max_height = 300;
$scale_x = $max_width / $title_width;
$scale_y = $max_height / $title_height;
$title_size = $size * min($scale_x, $scale_y, $scale_max);
echo "title font size :: $title_size\n\n";
echo $title_text."\n";

// create entry title
$color = create_color($pal['color1'], $img);
$title_y = floor($title_size * 0.92);
$bbox = imagettftext($img, $title_size, 0, 25, $title_y, $color, $font, $title_text); 

// figure out botbr name size
if (count($data['authors']) > 1) {
	$author_list = [];
	foreach ($data['authors'] as $author) $author_list[] = $author['name'];
	$noob_text = implode(' & ', $author_list);
}
else $noob_text = $data['botbr']['name'];
$wrap_at = get_wrap_at($noob_text);
$noob_text = wordwrap($noob_text, $wrap_at, "\n\r");
$noob_dim = imagettfbbox($size, 0, $font, $noob_text);
$noob_width = $noob_dim[2];
$noob_height = "\n". $size * (substr_count($noob_text, "\n") + 2);
$noob_left_padding = 75;
$max_width -= $noob_left_padding;
// art is at y=522
// title at y=108
// 522-108=414
$max_height = 420 - $title_y + 42;
$scale_x = $max_width / $noob_width;
$scale_y = $max_height / $title_height;
$noob_size = $size * min($scale_x, $scale_y, $scale_max);
echo "botbr font size :: $noob_size\n\n";
echo $noob_text."\n";

// create botbr name
$color = create_color($pal['color2'], $img);
$name_y = floor($noob_size * 0.92) + $bbox[1] + 25;
imagettftext($img, $noob_size, 0, $noob_left_padding, $name_y + 27, $color, $font, $noob_text); 
imagepng($img, 'assets/title.png');
imagedestroy($img);


# format icon and text

echo "\nCREATING FORMAT INFO :: \n";
$font = './Passion_One/PassionOne-Regular.ttf';
$size = 32;
$text = $data['format']['title'];
echo $text."\n";
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

echo "\nCREATING BATTLE DESCRIPTOR :: \n";
$size = 32;
$text = 'submitted to '.$data['battle']['title'];
$text = wordwrap($text, 50, "\n");
$text .= "\n".'                           on '.substr($data['datetime'], 0, 10);
echo $text."\n";
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

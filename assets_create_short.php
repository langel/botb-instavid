<?php

# grok data

$data = json_decode(file_get_contents('assets/data.json'), TRUE);

system('curl -k https://battleofthebits.com/api/v1/palette/load/'.$data['botbr']['palette_id'].' > assets/pal.json');
$pal = json_decode(file_get_contents('assets/pal.json'), TRUE);
print_r($pal);

// returns x,y array of box dimensions
function get_text_dimensions($size, $angle, $font, $text) {
	$text_dim = imagettfbbox($size, $angle, $font, $text);
	$dim = [
		abs($text_dim[6]) + abs($text_dim[2]),
		abs($text_dim[7]) + abs($text_dim[3])
	];
	return $dim;
}

function get_wrapped_words($str, $wrap_at, $cut_long_wrds = false) {
	return wordwrap($str, floor($wrap_at), "\n\r");
}

function get_wrapped_text($str) {
	$wrap_at = 24;
	$str_len = strlen($str);
	if ($str_len > 80) $wrap_at = 40;
	if ($str_len > 96) $wrap_at = 48;
	if ($str_len > 112) $wrap_at = 56;
	$text = $str;
	echo "  wrappable string length : $str_len\n";
	if ($str_len > $wrap_at) {
		//$wrap_at = $str_len * 0.56;
		$text = get_wrapped_words($str, $wrap_at);
	}
	/*
	if (substr_count($text, "\n\r") > 1) {
		$wrap_at = $str_len * 0.666;
		$text = get_wrapped_words($str, $wrap_at);
	}
	if (substr_count($text, "\n\r") > 1) {
		$wrap_at = $str_len * 0.75;
		$text = get_wrapped_words($str, $wrap_at);
	}
	if (substr_count($text, "\n\r") > 1) {
		$wrap_at = $str_len * 0.56;
		$text = get_wrapped_words($str, $wrap_at, true);
	}
	*/
	echo "  wrapping lines at $wrap_at characters\n\n";
	return $text;
}

echo "\nCREATING BACKGROUND :: \n";
system('wget --no-check-certificate https://battleofthebits.com/disk/debris/botb_bg.png -O assets/botb_bg.png');
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
		$r = $s[0] - (int)((($s[0]-$e[0])/$steps)*$i);
		$g = $s[1] - (int)((($s[1]-$e[1])/$steps)*$i);
		$b = $s[2] - (int)((($s[2]-$e[2])/$steps)*$i);
		$color = imagecolorallocate($img,$r,$g,$b);
		imagefilledrectangle($img,$x,$y+$i,$x1,$y+$i+1,$color);
	}
	return true;
}
$imgWidth = 1080;
$mp3_length = system("ffprobe assets/mp3 2>&1 | grep Duration|awk '{print $2}' | tr -d , | awk -F: '{ print ($1 * 3600) + ($2 * 60) + $3 }'");
$imgHeight = 1920 + floor($mp3_length * 20);
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
$text = 'battleofthebits.com';
$font = './arial-black.ttf';
$size = 88;
$spacing = -11;
function create_color($hex, $img) {
	$r = hexdec(substr($hex,0,2));
	$g = hexdec(substr($hex,2,2));
	$b = hexdec(substr($hex,4,2));
	return imagecolorallocatealpha($img,$r,$g,$b,0);
}
for ($k = 0; $k < 5; $k++) {
	$img = imagecreatetruecolor(988, 145);
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

echo "\nCREATING TITLE TEXT :: \n";
$font = './Racing_Sans_One/RacingSansOne-Regular.ttf';
$size = 72;
$scale_max = 2.0;


// create title/n00b image object
$img = imagecreatetruecolor(864, 350);
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);

function imagettftext_centered($img, $size, $angle, $y, $color, $font, $text) {
    $lines = explode("\n", $text);
    $line_height = $size * 1.2;
    foreach ($lines as $i => $line) {
        $bbox = imagettfbbox($size, $angle, $font, $line);
        $text_width = $bbox[2] - $bbox[0];
        $x = (imagesx($img) / 2) - ($text_width / 2);
        $line_y = $y + $i * $line_height;
        imagettftext($img, $size, $angle, $x, $line_y, $color, $font, $line);
    }
}

// title stuff
$title_text = get_wrapped_text($data['title']);
$title_dim = get_text_dimensions($size, 0, $font, $title_text);
$title_width = $title_dim[0];
$title_height = $title_dim[1];

$max_width = 1600 - 716 - 108;
$max_height = 256;
$scale_x = $max_width / $title_width;
$scale_y = $max_height / $title_height;
$title_size = $size * min($scale_x, $scale_y, $scale_max);
echo $title_text."\n";
echo "title font size :: $title_size\n\n";
echo "title_dim: $title_width x $title_height\n";

// create entry title
$color = create_color($pal['color1'], $img);
$title_y = floor($title_size * 0.92);

imagettftext_centered($img, $title_size, 0, $title_y, $color, $font, $title_text);

// botbr text
echo "\nCREATING AUTHORS TEXT :: \n";
if (count($data['authors']) > 1) {
    $author_list = [];
    foreach ($data['authors'] as $author) $author_list[] = $author['name'];
    $noob_text = implode(' & ', $author_list);
} else {
    $noob_text = $data['botbr']['name'];
}
$noob_text = get_wrapped_text($noob_text);

$max_width = imagesx($img) - 75;
$max_height = imagesy($img) - $title_y - $title_height - 27;

$noob_dim = get_text_dimensions($size, 0, $font, $noob_text);
$noob_width = $noob_dim[0];
$noob_height = $noob_dim[1];
echo "noob_dim: $noob_width x $noob_height\n";

$scale_x = $max_width / $noob_width;
$scale_y = $max_height / $noob_height;
$noob_size = $size * min($scale_x, $scale_y, $scale_max);

$name_y = $title_y + $title_height + ($noob_size * 0.92);
echo "y offset = $name_y\n\n";
echo "nooob     max_width: $max_width     noob_width: $noob_width     max_height :$max_height     noob_height: $noob_height\n";
echo "botbr font size :: $noob_size\n\n";
echo $noob_text."\n";
// create botbr name
$color = create_color($pal['color2'], $img);
imagettftext_centered($img, $noob_size, 0, $name_y, $color, $font, $noob_text);

imagepng($img, 'assets/title.png');
imagedestroy($img);



# format icon and text

echo "\nCREATING FORMAT INFO :: \n";
$font = './Passion_One/PassionOne-Regular.ttf';
$size = 32;
$text = $data['format']['title'];
echo $text."\n";
$text_dim = imagettfbbox($size, 0, $font, $text);
$img = imagecreatetruecolor($text_dim[2]+88,105);
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

echo "\nCREATING BATTLE DESCRIPTOR :: \n";
$text = 'submitted to' . "\n" . wordwrap($data['battle']['title'], 20, "\n", true);
$text .= "\non " . substr($data['datetime'], 0, 10);
echo $text."\n";

$lines = explode("\n", $text);

$line_height = $size * 1.2;
$img_height = count($lines) * $line_height + 20;
$img_width = 0;

foreach ($lines as $line) {
    $dim = imagettfbbox($size, 0, $font, $line);
    $line_width = $dim[2] - $dim[0];
    if ($line_width > $img_width) $img_width = $line_width;
}
$img_width += 20;

$img = imagecreatetruecolor($img_width, $img_height);
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);

$color = create_color($pal['color1'], $img);

$y = 10 + $size;
foreach ($lines as $line) {
    $dim = imagettfbbox($size, 0, $font, $line);
    $line_width = $dim[2] - $dim[0];
    $x = ($img_width - $line_width) / 2;
    imagettftext($img, $size, 0, $x, $y, $color, $font, $line);
    $y += $line_height;
}

imagepng($img, 'assets/battle-time.png');
imagedestroy($img);

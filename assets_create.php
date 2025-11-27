<?php

include('common.php');

$config = parse_ini_file('config.ini', true);

$system = $config['global']['system'];
$user_id = $config['cookie']['user_id'];
$serial = $config['cookie']['serial'];
$botbr_id = $config['cookie']['botbr_id'];

$cookie = '"Cookie: user_id='.$user_id.'; serial='.$serial.'; botbr_id='.$botbr_id.'"';

# grok data

$data = json_decode(file_get_contents('assets/data.json'), TRUE);

system('curl -k https://battleofthebits.com/api/v1/palette/load/'.$data['botbr']['palette_id'].' > assets/pal.json');
$pal = json_decode(file_get_contents('assets/pal.json'), TRUE);
print_r($pal);


echo "\nCREATING BACKGROUND :: \n";
system('wget '.$cookie.' --no-check-certificate https://battleofthebits.com/disk/debris/botb_bg.png -O assets/botb_bg.png');

$mp3_length = trim(shell_exec("ffprobe -v quiet -show_entries format=duration -of csv=p=0 assets/mp3"));
echo "media length :: $mp3_length seconds\n";

$is_short = False;
if ($mp3_length < 60) {
    $is_short = True;
}

if ($is_short) {
    $imgWidth = 1080;
    $imgHeight = $imgHeight = 1920 + floor($mp3_length * 20);
} else {
    $imgWidth = 1920;
    $imgHeight = 1080 + floor($mp3_length * 20);
}

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
if ($system == 'windows') {
    $font = __DIR__ . '/arial-black.ttf';
} else {
    $font = './arial-black.ttf';
}

if ($is_short) {
    $size = 88;
    $spacing = -11;
} else {
    $size = 110;
    $spacing = -15;
}

for ($k = 0; $k < 5; $k++) {
    if ($is_short) {
        $img = imagecreatetruecolor(988, 145);
    } else {
        $img = imagecreatetruecolor(1704, 250);
    }
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
if ($system == 'windows') {
    $font = __DIR__ . '/Racing_Sans_One/RacingSansOne-Regular.ttf';
} else {
    $font = './Racing_Sans_One/RacingSansOne-Regular.ttf';
}
$size = 72;
$scale_max = 2.0;


// create title/n00b image object
if ($is_short) {
    $img = imagecreatetruecolor(864, 350);
} else {
    $img = imagecreatetruecolor(1146,600);
}
imagesavealpha($img, true);
$trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $trans);

// figure out title size
$title_text = get_wrapped_text($data['title']);
$title_dim = get_text_dimensions($size, 0, $font, $title_text);
$title_width = $title_dim[0];
$title_height = $title_dim[1];

if ($is_short) {
    $max_width = 1600 - 716 - 108;
} else {
    $max_width = 1920 - 716 - 108;
}

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

if ($is_short) {
    imagettftext_centered($img, $title_size, 0, $title_y, $color, $font, $title_text);
} else {
    $bbox = imagettftext($img, $title_size, 0, 25, $title_y, $color, $font, $title_text);
}


// figure out botbr name size
echo "\nCREATING AUTHORS TEXT :: \n";
if (count($data['authors']) > 1) {
    $author_list = [];
    foreach ($data['authors'] as $author) $author_list[] = $author['name'];
    $noob_text = implode(' & ', $author_list);
} else {
    $noob_text = $data['botbr']['name'];
}
$noob_text = get_wrapped_text($noob_text);

$noob_dim = get_text_dimensions($size, 0, $font, $noob_text);
$noob_width = $noob_dim[0];
$noob_height = $noob_dim[1];
echo "noob_dim: $noob_width x $noob_height\n";
$noob_left_padding = 75;

// art is at y=522
// title at y=108
// 522-108=414

if ($is_short) {
    $max_width = imagesx($img) - 75;
    $max_height = imagesy($img) - $title_y - $title_height - 27;
} else {
    $max_width -= $noob_left_padding;
    $max_height = 420 - $title_y - $title_height - (27*2);
}

echo "nooob     max_width: $max_width     noob_width: $noob_width     max_height :$max_height     noob_height: $noob_height\n";
$scale_x = $max_width / $noob_width;
$scale_y = $max_height / $noob_height;
echo "scale_x: $scale_x    scale_y: $scale_y     scale_max: $scale_max\n\n";
$noob_size = $size * min($scale_x, $scale_y, $scale_max);
echo "botbr font size :: $noob_size\n\n";
echo $noob_text."\n";

// create botbr name
$color = create_color($pal['color2'], $img);
if ($is_short) {
    $name_y = $title_y + $title_height + ($noob_size * 0.92);
    imagettftext_centered($img, $noob_size, 0, $name_y, $color, $font, $noob_text);
} else {
    $name_y = floor($noob_size * 0.92) + $bbox[1] + 25;
    imagettftext($img, $noob_size, 0, $noob_left_padding, $name_y + 27, $color, $font, $noob_text);
}
echo "y offset = $name_y\n\n";
imagepng($img, 'assets/title.png');
imagedestroy($img);


# format icon and text

echo "\nCREATING FORMAT INFO :: \n";
if ($system == 'windows') {
    $font = __DIR__ . '/Passion_One/PassionOne-Regular.ttf';
} else {
    $font = './Passion_One/PassionOne-Regular.ttf';
}
$size = 32;
$text = $data['format']['title'];
echo $text."\n";
$text_dim = imagettfbbox($size, 0, $font, $text);
if ($is_short) {
    $img = imagecreatetruecolor($text_dim[2]+88,105);
} else {
    $img = imagecreatetruecolor($text_dim[2]+88,96);
}
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
if ($is_short) {
    $text = 'submitted to' . "\n" . wordwrap($data['battle']['title'], 20, "\n", true);
    $text .= "\non " . substr($data['datetime'], 0, 10);
} else {
    $text = 'submitted to '.$data['battle']['title'];
    $text = wordwrap($text, 50, "\n");
    $text .= "\n".'                           on '.substr($data['datetime'], 0, 10);
}
echo $text."\n";
if ($is_short) {
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
} else {
    $text_dim = imagettfbbox($size, 0, $font, $text);
    $img = imagecreatetruecolor($text_dim[2], 246);
    imagesavealpha($img, true);
    $trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $trans);
    $color = create_color($pal['color1'], $img);
    $y = floor($size * 1.1);
    imagettftext($img, $size, 0, 0, $y, $color, $font, $text);
}

imagepng($img, 'assets/battle-time.png');
imagedestroy($img);

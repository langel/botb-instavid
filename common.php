<?php

function create_color($hex, $img) {
	$r = hexdec(substr($hex,0,2));
	$g = hexdec(substr($hex,2,2));
	$b = hexdec(substr($hex,4,2));
	return imagecolorallocatealpha($img,$r,$g,$b,0);
}


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

<?php

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
	return wordwrap($str, $wrap_at, "\n\r");
}

function get_wrapped_text($str) {
	$wrap_at = 24;
	$str_len = strlen($str);
	$text = $str;
	echo "  wrappable string length : $str_len\n";
	if ($str_len > $wrap_at) {
		$wrap_at = $str_len * 0.56;
		$text = get_wrapped_words($str, $wrap_at);
	}
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
	echo "  wrapping lines at $wrap_at characters\n\n";
	return $text;
}

<?php

$config = parse_ini_file('config.ini', true);

$system = $config['global']['system'];
$user_id = $config['cookie']['user_id'];
$serial = $config['cookie']['serial'];
$botbr_id = $config['cookie']['botbr_id'];

$cookie = '"Cookie: user_id='.$user_id.'; serial='.$serial.'; botbr_id='.$botbr_id.'"';
if ($system == 'windows') {
    $convert = 'magick';
} else {
    $convert = 'convert';
}

$data = json_decode(file_get_contents('assets/data.json'), TRUE);
print_r($data);

$orientation = 'auto';
if (isset($argv[1])) {
    $arg = strtolower(trim($argv[1]));
    if (in_array($arg, ['auto', 'wide', 'vertical'], true)) {
        $orientation = $arg;
    }
}


print "\nHANDLING BATTLE COVER ART ::\n";
$battle_art_source_url = (string)$data['battle']['cover_art_url'];
$battle_art_meta_file = 'assets/battle_art.source';
$battle_art_needs_refresh = true;
if (file_exists('assets/battle-art450') && file_exists($battle_art_meta_file)) {
    $battle_art_cached_source = trim((string)file_get_contents($battle_art_meta_file));
    if ($battle_art_cached_source === $battle_art_source_url) {
        $battle_art_needs_refresh = false;
        print "using cached processed battle art (source unchanged)\n";
    }
}
if ($battle_art_needs_refresh) {
    system('wget --header='.$cookie.' --no-check-certificate "'.$battle_art_source_url.'" -O '.'assets/battle_art');
    print "scanning image...";
    system($convert.' '.'assets/battle_art -coalesce '.'assets/battle_art_temp');
    $dimensions = system("identify -format '%wx%h' assets/battle_art_temp[0]");
    print "resizing from $dimensions to 450x450\n";
    system($convert.' -size '.$dimensions.' '.'assets/battle_art_temp -filter box -resize 450x450 '.'assets/battle-art450');
    file_put_contents($battle_art_meta_file, $battle_art_source_url . PHP_EOL);
}
// check if its animated
$battle_art_cli = '';
$battle_art_frames = exec("identify assets/battle-art450 | sed -n 'p;$=' | tail -1");
if ((int)$battle_art_frames > 1) {
	print "BATTLE ART :: $battle_art_frames animated GIF frames detected\n";
	$battle_art_cli .= '-ignore_loop 0 ';
}
else print "BATTLE ART :: still image \n";


print "\nHANDLING FORMAT ICON ::\n";
$format_icon_source_url = (string)$data['format']['icon_url'];
$format_icon_meta_file = 'assets/format_icon.source';
$format_icon_needs_refresh = true;
if (file_exists('assets/format_icon.png') && file_exists($format_icon_meta_file)) {
    $format_icon_cached_source = trim((string)file_get_contents($format_icon_meta_file));
    if ($format_icon_cached_source === $format_icon_source_url) {
        $format_icon_needs_refresh = false;
        print "using cached format icon (source unchanged)\n";
    }
}
if ($format_icon_needs_refresh) {
    system('wget --header='.$cookie.' --no-check-certificate '.$format_icon_source_url.' -O '.'assets/format_icon');
    system($convert.' '.'assets/format_icon -filter box -resize 64x64 -coalesce '.'assets/format_icon.png');
    file_put_contents($format_icon_meta_file, $format_icon_source_url . PHP_EOL);
}


print "\nDONLOAD TEH MP3 ::\n";
system('wget --header='.$cookie.' --no-check-certificate "https://battleofthebits.com/player/EntryPlay/'.$data['id'].'" -O assets/mp3');
$mp3_length = trim(shell_exec("ffprobe -v quiet -show_entries format=duration -of csv=p=0 assets/mp3"));
echo "media length :: $mp3_length seconds\n";

$target_lufs = -14;
$target_lra = 11;
$target_tp = -1.5;
$normalized_wav_path = 'assets/normalized.wav';
if (!file_exists($normalized_wav_path) || filemtime($normalized_wav_path) < filemtime('assets/mp3')) {
    normalize_audio_loudnorm_two_pass('assets/mp3', $normalized_wav_path, $target_lufs, $target_lra, $target_tp, $system);
} else {
    echo "reusing cached normalized wav\n";
}
$audio_input_path = $normalized_wav_path;

$is_short = False;
if ($mp3_length < 90) {
    $is_short = True;
}
if ($orientation === 'wide') {
    $is_short = False;
}
if ($orientation === 'vertical') {
    $is_short = True;
}
echo "video orientation mode :: $orientation (" . ($is_short ? "vertical" : "wide") . ")\n";


print "\nHANDLING AVATARS ::\n";
$author_count = count($data['authors']);
$avatar_resize_to = 500;
if ($author_count > 1) $avatar_resize_to = 250;
if ($author_count > 5) $avatar_resize_to = 125;
$assets_cli = $setpts = $position = '';
$i = 0;
foreach ($data['authors'] as $author) {
	$id = $author['id'];
	$source_file = 'assets/avatar'.$id;
	$avatar_string = substr($author['avatar_from_time'], strpos($author['avatar_from_time'], '/', -1));
	$temp_file = "assets/avatarTEMP".$author['id'];
	$target_file = "assets/avatar".$author['id'];
    $avatar_source_url = 'https://battleofthebits.com/'.$avatar_string;
    $avatar_meta_file = $target_file . '.source';
    $avatar_needs_refresh = true;
    if (file_exists($target_file) && file_exists($avatar_meta_file)) {
        $avatar_cached_source = trim((string)file_get_contents($avatar_meta_file));
        if ($avatar_cached_source === $avatar_source_url) {
            $avatar_needs_refresh = false;
            print "reusing cached avatar for author $id\n";
        }
    }
    if ($avatar_needs_refresh) {
	    system('wget --header='.$cookie.' --no-check-certificate '.$avatar_source_url.' -O '.$source_file);
	    print "coalescing avatar to $temp_file\n";
	    system($convert.' '.$source_file.' -coalesce '.$temp_file);
	    print "resizing avatar to $target_file\n";
	    system($convert.' '.$temp_file.' -filter box -resize '.$avatar_resize_to.'x'.$avatar_resize_to.' '.$target_file);
        file_put_contents($avatar_meta_file, $avatar_source_url . PHP_EOL);
    }
	// check if its animated
	$avatar_frames = exec("identify $target_file | sed -n 'p;$=' | tail -1");
	if ((int)$avatar_frames > 1) {
		print "AVATAR :: $avatar_frames animated GIF frames detected\n";
		$assets_cli .= '-ignore_loop 0 ';
	}
	else print "AVATAR :: still image \n";
	$assets_cli .= '-i '.$target_file.' ';
	// create all the other ffmpeg crap
	$setpts .= '['.($i + 6).':v] setpts=PTS-STARTPTS [avatar'.$id.'];'."\n";
	$x = $y = 108;
	if ($avatar_resize_to == 250) {
		if ($i % 2) $x += 250;
		if ($author_count == 2 && $i == 1) $y = 108 + 250;
		else $y += floor($i / 2) * 250;
	}
	if ($avatar_resize_to == 125) {
		$spacing = 125 + floor(125 / 4);
		$x += ($i % 3) * $spacing;
		$y += floor($i / 3) * ($spacing * 0.9);
	}
    if ($is_short) {
        $position .= " [ava0".$i."];\n[ava0".$i."][avatar".$id."] overlay=1:x='540-(overlay_w)*0.5':y=100";
    } else {
	    $position .= " [ava0".$i."];\n[ava0".$i."][avatar".$id."] overlay=1:x=".$x.":y=".$y;
	}
	$i++;
}

$battle_offset = ($author_count < 6) ? 960 : 1020;
/*
# WORKING FFMPEG CALL LONG VERSION
ffmpeg -i assets/background.png -i assets/battle-art300-%03d.png -i assets/format.png -loop 1 -r 15 -i assets/botblogo-%01d.png -i assets/title.png -i assets/battle-time.png $loop -i assets/avatar500 -i assets/mp3 -filter_complex "
nullsrc=size=1920x1080 [base];
[0:v] setpts=PTS-STARTPTS [bg];
[1:v] setpts=PTS-STARTPTS [battle];
[2:v] setpts=PTS-STARTPTS [format];
[3:v] setpts=PTS-STARTPTS [botblogo];
[4:v] setpts=PTS-STARTPTS [title];
[5:v] setpts=PTS-STARTPTS [battle_time];
[6:v] setpts=PTS-STARTPTS [avatar00];
[base][bg] overlay=y=-'((t+0.001)/$length)*(h-H)' [tmp1];
[tmp1][battle] overlay=1:x=1362:y=522 [tmp2];
[tmp2][format] overlay=1:x='960-(overlay_w-50)*0.5':y=562 [tmp3];
[tmp3][botblogo] overlay=1:x=100:y=830 [tmp4];
[tmp4][title] overlay=1:x=691:y=108 [tmp5];
[tmp5][battle_time] overlay=1:x='960-(overlay_w+100)*0.5':y=666 [ava00];
[ava00][avatar00] overlay=1:x=108:y=108
" -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -r 30000/1001 -t "${length}" "${entry_id}.mp4"

# WORKING FFMPEG CALL SHORT VERSION
ffmpeg -i assets/background.png -i assets/battle-art300-%03d.png -i assets/format.png -loop 1 -r 15 -i assets/botblogo-%01d.png -i assets/title.png -i assets/battle-time.png -i assets/botbr.png $loop -i assets/avatar500 -i assets/mp3 -filter_complex "
nullsrc=size=1080x1920 [base];
[0:v] setpts=PTS-STARTPTS [bg];
[1:v] setpts=PTS-STARTPTS [battle];
[2:v] setpts=PTS-STARTPTS [format];
[3:v] setpts=PTS-STARTPTS [botblogo];
[4:v] setpts=PTS-STARTPTS [title];
[5:v] setpts=PTS-STARTPTS [battle_time];
[6:v] setpts=PTS-STARTPTS [avatar00];
[base][bg] overlay=y=-'((t+0.001)/65.18)*(h-H)' [tmp1];
[tmp1][battle] overlay=1:x=522:y=1172 [tmp2];
[tmp2][format] overlay=1:x='540-(overlay_w)*0.5':y=1020 [tmp3];
[tmp3][botblogo] overlay=1:x=46:y=1700 [tmp4];
[tmp4][title] overlay=1:x='540-(overlay_w)*0.5':y=666 [tmp5];
[tmp5][battle_time] overlay=1:x='270-(overlay_w)*0.5':y=1300 [ava00];
[ava00][avatar00] overlay=1:x='540-(overlay_w)*0.5':y=108
" -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -r 30000/1001 -t "${length}" "${entry_id}.mp4"
*/

if ($is_short) {
    $vid_dimensions = 'nullsrc=size=1080x1920 [base];';
    $tmp1 = '[battle] overlay=1:x=522:y=1172';
    $tmp2 = "[format] overlay=1:x='540-(overlay_w)*0.5':y=1028";
    $tmp3 = '[botblogo] overlay=1:x=46:y=1700';
    $tmp4 = "[title] overlay=1:x='540-(overlay_w)*0.5':y=640";
    $tmp5 = "[battle_time] overlay=1:x='270-(overlay_w)*0.5':y=1300".$position;
} else {
    $vid_dimensions = 'nullsrc=size=1920x1080 [base];';
    $tmp1 = '[battle] overlay=1:x=1362:y=522';
    $tmp2 = "[format] overlay=1:x='960-(overlay_w-50)*0.5':y=562";
    $tmp3 = '[botblogo] overlay=1:x=100:y=830';
    $tmp4 = '[title] overlay=1:x=691:y=108';
    $tmp5 = "[battle_time] overlay=1:x='".$battle_offset."-(overlay_w+100)*0.5':y=666".$position;
}

$ffmpeg_call = 'ffmpeg -i assets/background.png '.$battle_art_cli.'-i assets/battle-art450 -i assets/format.png -loop 1 -r 15 -i assets/botblogo-%01d.png -i assets/title.png -i assets/battle-time.png '.$assets_cli.' -i '.escapeshellarg($audio_input_path).' -filter_complex "
'.$vid_dimensions.'
[0:v] setpts=PTS-STARTPTS [bg];
[1:v] setpts=PTS-STARTPTS [battle];
[2:v] setpts=PTS-STARTPTS [format];
[3:v] setpts=PTS-STARTPTS [botblogo];
[4:v] setpts=PTS-STARTPTS [title];
[5:v] setpts=PTS-STARTPTS [battle_time];
'.$setpts."[base][bg] overlay=y=-'((t+0.001)/".$mp3_length.")*(h-H)' [tmp1];
[tmp1]".$tmp1." [tmp2];
[tmp2]".$tmp2." [tmp3];
[tmp3]".$tmp3." [tmp4];
[tmp4]".$tmp4." [tmp5];
[tmp5]".$tmp5.'
" -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -ar 48000 -ac 2 -pix_fmt yuv420p -r 30000/1001 -t '.$mp3_length.' '.$data['id'].'.mp4';

print $ffmpeg_call;

file_put_contents('assets/ffmpeg_call', $ffmpeg_call);

function normalize_audio_loudnorm_two_pass(string $input_path, string $output_path, float $target_lufs, float $target_lra, float $target_tp, string $system): void
{
    $pass1_filter = 'loudnorm=I='.$target_lufs.':LRA='.$target_lra.':TP='.$target_tp.':print_format=json';
    $null_sink = (strtolower($system) === 'windows') ? 'NUL' : '/dev/null';
    $cmd = 'ffmpeg -hide_banner -nostats -i '.escapeshellarg($input_path).' -af '.escapeshellarg($pass1_filter).' -f null '.escapeshellarg($null_sink).' 2>&1';
    $out = shell_exec($cmd);
    if (!is_string($out) || trim($out) === '') {
        throw new RuntimeException('Two-pass loudnorm analysis produced no output.');
    }
    if (preg_match('/\{\s*"input_i"[\s\S]*?"target_offset"\s*:\s*"[^"]*"\s*\}/', $out, $m) !== 1) {
        throw new RuntimeException('Unable to parse loudnorm stats block from ffmpeg output.');
    }
    $stats = json_decode($m[0], true);
    if (!is_array($stats)) {
        throw new RuntimeException('Failed decoding loudnorm stats JSON.');
    }
    foreach (['input_i', 'input_lra', 'input_tp', 'input_thresh', 'target_offset'] as $required) {
        if (!isset($stats[$required]) || trim((string)$stats[$required]) === '') {
            throw new RuntimeException('Missing loudnorm measured value: '.$required);
        }
    }
    $pass2_filter = 'loudnorm=I='.$target_lufs.':LRA='.$target_lra.':TP='.$target_tp.':linear=true'
        .':measured_I='.$stats['input_i']
        .':measured_LRA='.$stats['input_lra']
        .':measured_TP='.$stats['input_tp']
        .':measured_thresh='.$stats['input_thresh']
        .':offset='.$stats['target_offset'];
    system(
        'ffmpeg -y -i '.escapeshellarg($input_path)
        .' -af '.escapeshellarg($pass2_filter)
        .' -ar 44100 -c:a pcm_s16le '.escapeshellarg($output_path),
        $exit_code
    );
    if ((int)$exit_code !== 0) {
        throw new RuntimeException('Loudnorm apply pass failed while creating normalized wav.');
    }
}

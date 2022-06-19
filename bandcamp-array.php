<?php

if (count($argv) < 3) {
	echo "give it a botb release url and corresponding battle_id -- it'll try to find entry_id's and spew them onscreen\n";
	die();
}

$page = file_get_contents($argv[1]);
$battle_id = $argv[2];

$title = substr($page, strpos($page, '"trackTitle">') + 13);
$title = substr($title, 0, strpos($title, '</h2>'));
$title = trim($title);

echo "$battle_id : $title \n";

$track_count = substr_count($page, "track-title");

echo "$track_count tracks found \n\n";

$track = $page;
$tracks = [];
for ($i = 0; $i < $track_count; $i++) {
	$page = substr($page, strpos($page, "track-title") + 13);
	$page = substr($page, strpos($page, "-") + 2);
	$track = html_entity_decode(substr($page, 0, strpos($page, "</")));
	echo "$track\n";
	$url = 'https://battleofthebits.org/api/v1/entry/list?filters=battle_id~'.$battle_id.'^title~'.urlencode($track);
	echo "$url\n";
	system('curl -k '.$url.' > trackdata.json', $ass);
	$trackdata = json_decode(file_get_contents('trackdata.json'), true)[0];
	echo $trackdata['id'].' '.$trackdata['authors_display'].' - '.$trackdata['title']."\n";
	$tracks[] = $trackdata;
}
unlink('trackdata.json');

$track_ids = [];
echo "\n\n";

for ($i = 0; $i < $track_count; $i++) {
	echo $tracks[$i]['id'].' '.$tracks[$i]['authors_display'].' - '.$tracks[$i]['title']."\n";
	$track_ids[] = $tracks[$i]['id'];
}

echo "\n\n";
echo implode(" ", $track_ids);
echo "\n";

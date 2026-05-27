#!/usr/bin/env php
<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php comp_builder.php <battle_id> [-p|--preview]\n");
    exit(1);
}

$battle_id = 0;
$preview_only = false;
for ($i = 1; $i < $argc; $i++) {
    $arg = trim((string)$argv[$i]);
    if ($arg === '-p' || $arg === '--preview') {
        $preview_only = true;
        continue;
    }
    if (ctype_digit($arg) && $battle_id === 0) {
        $battle_id = (int)$arg;
        continue;
    }
    fwrite(STDERR, "Invalid argument: {$arg}\n");
    fwrite(STDERR, "Usage: php comp_builder.php <battle_id> [-p|--preview]\n");
    exit(1);
}
if ($battle_id <= 0) {
    fwrite(STDERR, "A valid numeric battle_id is required.\n");
    fwrite(STDERR, "Usage: php comp_builder.php <battle_id> [-p|--preview]\n");
    exit(1);
}

$config = parse_ini_file('config.ini', true);
if ($config === false) {
    fwrite(STDERR, "Missing or invalid config.ini\n");
    exit(1);
}

$system_type = $config['global']['system'] ?? '';
$convert_cmd = (strtolower($system_type) === 'windows') ? 'magick' : 'convert';
$user_id = $config['cookie']['user_id'] ?? '';
$serial = $config['cookie']['serial'] ?? '';
$botbr_id = $config['cookie']['botbr_id'] ?? '';

if ($user_id === '' || $serial === '' || $botbr_id === '') {
    fwrite(STDERR, "config.ini cookie values are required: user_id, serial, botbr_id\n");
    exit(1);
}

$cookie_header = "Cookie: user_id={$user_id}; serial={$serial}; botbr_id={$botbr_id}";
$api_base = 'https://battleofthebits.com/api/v1';
$build_started_at = microtime(true);

try {
    $battle = fetch_json("{$api_base}/battle/load/{$battle_id}", $cookie_header);
    $format_title_map = [];
    try {
        $format_title_map = fetch_format_title_map($api_base, $cookie_header);
    } catch (Throwable $ignored) {
        $format_title_map = [];
    }
    $battle_title = trim((string)($battle['title'] ?? ''));
    if ($battle_title === '') {
        throw new RuntimeException("Battle title is missing for battle_id {$battle_id}");
    }
    $release_date = format_battle_release_date_mmddyyyy($battle);

    $safe_battle_dir = sanitize_file_component($battle_title);
    if ($safe_battle_dir === '') {
        $safe_battle_dir = "battle_{$battle_id}";
    }

    $out_dir = $safe_battle_dir;
    if (is_dir($out_dir)) {
        $should_overwrite = prompt_overwrite_directory($out_dir);
        if (!$should_overwrite) {
            throw new RuntimeException("Output directory already exists and overwrite was declined: {$out_dir}");
        }
        remove_directory_recursive($out_dir);
    }

    $entries_raw = fetch_json("{$api_base}/entry/list/0/500?filters=battle_id~{$battle_id}", $cookie_header);
    $entries = normalize_entry_list($entries_raw);
    if (count($entries) === 0) {
        throw new RuntimeException("No entries returned for battle {$battle_id}");
    }

    $filtered = array_values(array_filter($entries, static function ($entry): bool {
        return ((int)get_value($entry, ['medium']) === 0) && ((int)get_value($entry, ['bit']) === 0);
    }));
    if (count($filtered) === 0) {
        throw new RuntimeException("No entries match medium==0 and bit==0 for battle {$battle_id}");
    }

    usort($filtered, 'compare_by_score_desc');

    $buckets = [];

    foreach ($filtered as $entry) {
        $format_token = (string)get_value($entry, ['format_token', 'format.token'], 'unknown');
        $period_id = (string)get_value($entry, ['period_id', 'period.id'], '0');
        $bucket_key = $format_token . '|' . $period_id;
        if (!isset($buckets[$bucket_key])) {
            $buckets[$bucket_key] = [];
        }
        $buckets[$bucket_key][] = $entry;
    }

    $botbr_cap = 5;
    $selection_result = build_selected_with_dynamic_top_bucket($filtered, $buckets, 3, $botbr_cap);
    $selected = $selection_result['selected'];
    $top_percent_count = $selection_result['top_percent_count'];
    $top_percent_base_count = $selection_result['top_percent_base_count'];
    $top_percent_bonus_count = $selection_result['top_percent_bonus_count'];

    if (count($selected) < 25) {
        $desired_count = prompt_desired_track_count(count($selected), count($filtered));
        if ($desired_count > count($selected)) {
            $selected = expand_selection_to_count($selected, $filtered, $desired_count, $botbr_cap);
            usort($selected, 'compare_by_score_desc');
        }
    }

    $highest_score_entry_id = (int)get_value($selected[0], ['id'], 0);
    $second_highest_score_entry_id = 0;
    if (count($selected) > 1) {
        $second_highest_score_entry_id = (int)get_value($selected[1], ['id'], 0);
    }

    $ordered = build_playlist_order($selected);
    if (count($ordered) === 0) {
        throw new RuntimeException("Selected entry set is empty after applying rules.");
    }
    $spacing_status = 'applied';
    $spacing_warning_lines = [];
    try {
        $ordered = enforce_botbr_spacing($ordered, 4, $highest_score_entry_id, $second_highest_score_entry_id);
    } catch (RuntimeException $spacing_error) {
        $spacing_status = 'bypassed';
        $ordered = enforce_anchor_positions($ordered, $highest_score_entry_id, $second_highest_score_entry_id);
        $spacing_warning_lines = build_spacing_violation_summary_lines($ordered, 4, 10);
        $continue_without_spacing = prompt_continue_without_spacing($spacing_error->getMessage(), $spacing_warning_lines);
        if (!$continue_without_spacing) {
            throw new RuntimeException("Aborted due to unsatisfied spacing rule.");
        }
    }

    $unique_format_tokens = [];
    foreach ($ordered as $entry) {
        $token = (string)get_value($entry, ['format_token', 'format.token'], 'unknown');
        $unique_format_tokens[$token] = true;
    }
    $single_format_token_across_selection = count($unique_format_tokens) === 1;

    $pad_width = max(2, strlen((string)count($ordered)));
    $output = build_output_structure($out_dir, $preview_only);

    $track_filenames = [];
    $projected_total_seconds = 0;
    $track_plan = [];
    $include_format_in_track_titles = !$single_format_token_across_selection;
    foreach ($ordered as $index => $entry) {
        $track_no = $index + 1;
        $track_padded = str_pad((string)$track_no, $pad_width, '0', STR_PAD_LEFT);
        $format_token_raw = (string)get_value($entry, ['format_token', 'format.token'], 'unknown');
        $format_token_file = sanitize_file_component($format_token_raw);
        $title_raw = (string)get_value($entry, ['title'], 'untitled');
        $title_file = sanitize_file_component($title_raw);
        $author_names_raw = get_author_names_display($entry);
        $author_names_file = sanitize_file_component($author_names_raw);
        $format_segment = $single_format_token_across_selection ? '-' : "[{$format_token_file}]";
        $base_name = "{$track_padded} {$author_names_file} {$format_segment} {$title_file}";
        $track_filenames[] = $base_name . '.wav';
        $projected_total_seconds += (int)round((float)get_value($entry, ['length'], 0));
        $track_plan[] = [
            'entry' => $entry,
            'track_no' => $track_no,
            'track_padded' => $track_padded,
            'format_token' => $format_token_raw,
            'title' => $title_raw,
            'author_names' => $author_names_raw,
            'base_name' => $base_name,
            'title_display' => build_track_title_display($title_raw, $format_token_raw, $include_format_in_track_titles),
        ];
    }

    $playlist_report = build_preflight_log(
        $filtered,
        $buckets,
        $top_percent_count,
        $top_percent_base_count,
        $top_percent_bonus_count,
        $selected,
        $ordered,
        $track_filenames,
        $projected_total_seconds,
        $spacing_status,
        $spacing_warning_lines,
        $release_date,
        null
    );
    file_put_contents($output['root'] . DIRECTORY_SEPARATOR . 'playlist_report.txt', $playlist_report);
    echo $playlist_report;

    if ($preview_only) {
        $preview_elapsed_seconds = (int)round(max(0, microtime(true) - $build_started_at));
        $playlist_report = build_preflight_log(
            $filtered,
            $buckets,
            $top_percent_count,
            $top_percent_base_count,
            $top_percent_bonus_count,
            $selected,
            $ordered,
            $track_filenames,
            $projected_total_seconds,
            $spacing_status,
            $spacing_warning_lines,
            $release_date,
            $preview_elapsed_seconds
        );
        file_put_contents($output['root'] . DIRECTORY_SEPARATOR . 'playlist_report.txt', $playlist_report);
        echo "\nPREVIEW MODE: skipping media build steps.\n";
        exit(0);
    }

    $manifest_entries = [];
    $concat_lines = [];

    foreach ($track_plan as $planned_track) {
        $entry = $planned_track['entry'];
        $track_no = $planned_track['track_no'];
        $track_padded = $planned_track['track_padded'];
        $entry_id = (int)get_value($entry, ['id'], 0);
        if ($entry_id <= 0) {
            continue;
        }

        $score = (float)get_value($entry, ['score'], 0.0);
        $format_token = $planned_track['format_token'];
        $title = $planned_track['title'];
        $author_names = $planned_track['author_names'];
        $period_id = (string)get_value($entry, ['period_id', 'period.id'], '0');
        $base_name = $planned_track['base_name'];
        $mp3_temp = $output['temp'] . DIRECTORY_SEPARATOR . "{$track_padded}_{$entry_id}.mp3";
        $wav_out = $output['bandcamp'] . DIRECTORY_SEPARATOR . $base_name . '.wav';

        $audio_url = "https://battleofthebits.com/player/EntryPlay/{$entry_id}";
        run("curl -ksSL --header " . esc($cookie_header) . " " . esc($audio_url) . " -o " . esc($mp3_temp), "download mp3 for entry {$entry_id}");
        run(
            "ffmpeg -y -i " . esc($mp3_temp) . " -af " . esc('loudnorm=I=-14:LRA=11:TP=-1.5') . " -ar 44100 -c:a pcm_s16le " . esc($wav_out),
            "normalize to wav for entry {$entry_id}"
        );

        $track_video_root = "{$entry_id}.mp4";
        run("bash instavid.sh {$entry_id} wide", "render instavid for entry {$entry_id} (forced wide)");
        if (!file_exists($track_video_root)) {
            throw new RuntimeException("Expected video missing after instavid: {$track_video_root}");
        }

        $track_video = $output['youtube_tracks'] . DIRECTORY_SEPARATOR . "{$track_padded}_{$entry_id}.mp4";
        rename_or_fail($track_video_root, $track_video, "move rendered video for entry {$entry_id}");

        $normalized_video = $output['youtube_normalized'] . DIRECTORY_SEPARATOR . "{$track_padded}_{$entry_id}.norm.mp4";
        run(
            "ffmpeg -y -i " . esc($track_video) .
            " -vf " . esc("scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1") .
            " -r 30000/1001 -c:v libx264 -crf 20 -preset medium -pix_fmt yuv420p -c:a aac -b:a 192k -ar 48000 -ac 2 " . esc($normalized_video),
            "normalize video dimensions for entry {$entry_id}"
        );

        $normalized_video_abs = resolve_absolute_path($normalized_video);
        $concat_lines[] = "file '" . escape_concat_path($normalized_video_abs) . "'";
        $track_title_display = $planned_track['title_display'];
        $manifest_entries[] = [
            'track' => $track_no,
            'entry_id' => $entry_id,
            'score' => $score,
            'period_id' => $period_id,
            'format_token' => $format_token,
            'botbr' => $author_names,
            'title' => $title,
            'title_display' => $track_title_display,
            'length_seconds' => (int)round((float)get_value($entry, ['length'], 0)),
            'wav' => normalize_path($wav_out),
            'video' => normalize_path($track_video),
        ];
    }

    $battle_profile_url = "https://battleofthebits.org/arena/Battle/{$battle_id}";
    $release_date_long = format_battle_release_date_long($battle);
    if ($release_date_long === '') {
        $release_date_long = date('F j, Y');
    }
    $youtube_description_lines = [];
    $youtube_description_lines[] = "completed {$release_date_long}";
    $youtube_description_lines[] = $battle_profile_url;
    $youtube_description_lines[] = '';
    $running_seconds = 0;
    foreach ($manifest_entries as $manifest_entry) {
        $timestamp = format_seconds_compact_mmss_or_hhmmss($running_seconds);
        $youtube_description_lines[] = "{$timestamp} {$manifest_entry['botbr']} - {$manifest_entry['title_display']}";
        $running_seconds += (int)($manifest_entry['length_seconds'] ?? 0);
    }
    file_put_contents(
        $output['root'] . DIRECTORY_SEPARATOR . 'yt_description.txt',
        implode(PHP_EOL, $youtube_description_lines) . PHP_EOL
    );

    $cover_ext = detect_extension_from_url((string)($battle['cover_art_url'] ?? 'png'));
    $cover_src = $output['temp'] . DIRECTORY_SEPARATOR . "cover_source.{$cover_ext}";
    $cover_out = $output['bandcamp'] . DIRECTORY_SEPARATOR . "cover_art.{$cover_ext}";
    $cover_url = (string)($battle['cover_art_url'] ?? '');
    if ($cover_url === '') {
        throw new RuntimeException("Battle cover_art_url is missing.");
    }
    $cover_url = encode_url_path($cover_url);

    run("curl -ksSL --header " . esc($cookie_header) . " " . esc($cover_url) . " -o " . esc($cover_src), "download battle cover art");

    [$cover_w, $cover_h] = get_image_dimensions($cover_src);
    $scale = 1;
    while (($cover_w * $scale) < 1400 || ($cover_h * $scale) < 1400) {
        $scale++;
    }

    $scaled_w = $cover_w * $scale;
    $scaled_h = $cover_h * $scale;

    if ($scale > 1) {
        run(
            "{$convert_cmd} " . esc($cover_src) . " -filter point -resize {$scaled_w}x{$scaled_h}! " . esc($cover_out),
            "scale cover art by integer multiple {$scale}x"
        );
    } else {
        copy_or_fail($cover_src, $cover_out, "copy cover art without scaling");
    }

    $concat_file = $output['temp'] . DIRECTORY_SEPARATOR . 'concat_list.txt';
    file_put_contents($concat_file, implode(PHP_EOL, $concat_lines) . PHP_EOL);

    $compilation_name = sanitize_file_component($battle_title) . '_compilation.mp4';
    $compilation_out = $output['root'] . DIRECTORY_SEPARATOR . $compilation_name;
    run(
        "ffmpeg -y -f concat -safe 0 -i " . esc($concat_file) .
        " -c:v libx264 -crf 20 -preset medium -pix_fmt yuv420p -c:a aac -b:a 192k -ar 48000 -ac 2 " . esc($compilation_out),
        "concatenate compilation video"
    );

    $thumbnail_out = $output['root'] . DIRECTORY_SEPARATOR . 'yt_thumbnail.jpg';
    $thumbnail_bg = $output['temp'] . DIRECTORY_SEPARATOR . 'thumbnail_bg.png';
    $thumbnail_cover = $output['temp'] . DIRECTORY_SEPARATOR . 'thumbnail_cover.png';
    $thumbnail_cover_source = $cover_src;
    if (strtolower($cover_ext) === 'gif') {
        $thumbnail_cover_source = $output['temp'] . DIRECTORY_SEPARATOR . 'cover_source_frame0.png';
        run(
            "{$convert_cmd} " . esc($cover_src . '[0]') . " " . esc($thumbnail_cover_source),
            "extract first frame from gif cover art for thumbnail"
        );
        if (!file_exists($thumbnail_cover_source)) {
            throw new RuntimeException("Failed to extract first frame from GIF cover art for thumbnail.");
        }
    }
    $botb_pattern = $output['temp'] . DIRECTORY_SEPARATOR . 'botb_bg.png';
    run(
        "curl -ksSL --header " . esc($cookie_header) . " " . esc('https://battleofthebits.com/disk/debris/botb_bg.png') . " -o " . esc($botb_pattern),
        "download BotB background tile pattern"
    );
    create_tiled_image_background($botb_pattern, $thumbnail_bg, 1920, 1080);
    run(
        "{$convert_cmd} " . esc($thumbnail_cover_source) . " -resize x1080 " . esc($thumbnail_cover),
        "resize cover art for thumbnail foreground"
    );
    run(
        "{$convert_cmd} " . esc($thumbnail_bg) . " " . esc($thumbnail_cover) . " -gravity center -composite -quality 92 " . esc($thumbnail_out),
        "compose youtube thumbnail image"
    );

    foreach (glob($output['youtube_tracks'] . DIRECTORY_SEPARATOR . '*.mp4') ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob($output['youtube_normalized'] . DIRECTORY_SEPARATOR . '*.mp4') ?: [] as $file) {
        @unlink($file);
    }

    file_put_contents(
        $output['root'] . DIRECTORY_SEPARATOR . 'selected_entries.json',
        json_encode(
            [
                'battle_id' => $battle_id,
                'battle_title' => $battle_title,
                'selection_count' => count($manifest_entries),
                'entries' => $manifest_entries,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL
    );

    file_put_contents(
        $output['root'] . DIRECTORY_SEPARATOR . 'copy_links.html',
        build_copy_links_html($battle_title, $release_date, $manifest_entries, build_format_legend_lines($ordered, $format_title_map))
    );
    $total_elapsed_seconds = (int)round(max(0, microtime(true) - $build_started_at));
    file_put_contents(
        $output['root'] . DIRECTORY_SEPARATOR . 'playlist_report.txt',
        build_preflight_log(
            $filtered,
            $buckets,
            $top_percent_count,
            $top_percent_base_count,
            $top_percent_bonus_count,
            $selected,
            $ordered,
            $track_filenames,
            $projected_total_seconds,
            $spacing_status,
            $spacing_warning_lines,
            $release_date,
            $total_elapsed_seconds
        )
    );
    remove_directory_recursive($output['youtube']);
    remove_directory_recursive($output['temp']);

    echo "\nCOMP BUILDER COMPLETE\n";
    echo "Output directory: {$output['root']}\n";
    echo "Bandcamp WAV dir: {$output['bandcamp']}\n";
    echo "YouTube video: {$compilation_out}\n";
    echo "Track count: " . count($manifest_entries) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

function fetch_json(string $url, string $cookie_header): array
{
    $cmd = "curl -ksSL --header " . esc($cookie_header) . " " . esc($url);
    $json = shell_exec($cmd);
    if (!is_string($json) || trim($json) === '') {
        throw new RuntimeException("No response from URL: {$url}");
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON from URL: {$url}");
    }
    return $decoded;
}

function fetch_format_title_map(string $api_base, string $cookie_header): array
{
    $raw = fetch_json("{$api_base}/format/list/0/500", $cookie_header);
    $map = [];
    foreach ($raw as $format) {
        if (!is_array($format)) {
            continue;
        }
        $token = trim((string)($format['token'] ?? ''));
        if ($token === '') {
            continue;
        }
        $title = trim((string)($format['title'] ?? ''));
        if ($title === '') {
            $title = $token;
        }
        $map[$token] = $title;
    }
    return $map;
}

function normalize_entry_list(array $raw): array
{
    if (isset($raw[0]) && is_array($raw[0])) {
        return $raw;
    }
    if (isset($raw['entries']) && is_array($raw['entries'])) {
        return $raw['entries'];
    }
    if (isset($raw['data']) && is_array($raw['data'])) {
        return $raw['data'];
    }
    return [];
}

function compare_by_score_desc(array $a, array $b): int
{
    $score_a = (float)get_value($a, ['score'], 0.0);
    $score_b = (float)get_value($b, ['score'], 0.0);
    return $score_b <=> $score_a;
}

function get_value(array $entry, array $paths, $default = '')
{
    foreach ($paths as $path) {
        $segments = explode('.', $path);
        $cursor = $entry;
        $ok = true;
        foreach ($segments as $segment) {
            if (is_array($cursor) && array_key_exists($segment, $cursor)) {
                $cursor = $cursor[$segment];
            } else {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return $cursor;
        }
    }
    return $default;
}

function build_playlist_order(array $entries_by_score_desc): array
{
    $count = count($entries_by_score_desc);
    if ($count <= 1) {
        return $entries_by_score_desc;
    }

    $ordered = [];
    $ordered[] = $entries_by_score_desc[0];
    $remaining = array_slice($entries_by_score_desc, 1);

    $even_ranks = [];
    $odd_ranks = [];
    foreach ($remaining as $idx => $entry) {
        $rank = $idx + 2;
        if ($rank % 2 === 0) {
            $even_ranks[] = $entry;
        } else {
            $odd_ranks[] = $entry;
        }
    }

    $even_ascending = array_reverse($even_ranks);
    $max = max(count($odd_ranks), count($even_ascending));
    for ($i = 0; $i < $max; $i++) {
        if (isset($odd_ranks[$i])) {
            $ordered[] = $odd_ranks[$i];
        }
        if (isset($even_ascending[$i])) {
            $ordered[] = $even_ascending[$i];
        }
    }

    return $ordered;
}

function enforce_botbr_spacing(
    array $ordered_entries,
    int $min_tracks_between_same_botbr,
    int $fixed_first_entry_id = 0,
    int $fixed_last_entry_id = 0
): array
{
    if ($min_tracks_between_same_botbr <= 0 || count($ordered_entries) <= 1) {
        return enforce_anchor_positions($ordered_entries, $fixed_first_entry_id, $fixed_last_entry_id);
    }
    $entries = array_values($ordered_entries);
    $entry_author_keys = [];
    foreach ($entries as $idx => $entry) {
        $entry_author_keys[$idx] = get_botbr_keys($entry);
    }

    $remaining_indices = array_keys($entries);
    $prefix_indices = [];
    $cooldown = [];
    $required_last_idx = null;

    if ($fixed_first_entry_id > 0) {
        $fixed_first_idx = find_entry_index_by_id($entries, $fixed_first_entry_id);
        if ($fixed_first_idx === null) {
            throw new RuntimeException("Unable to enforce first-track anchor: entry {$fixed_first_entry_id} not found.");
        }
        $prefix_indices[] = $fixed_first_idx;
        $remaining_indices = array_values(array_filter($remaining_indices, static function ($idx) use ($fixed_first_idx) {
            return $idx !== $fixed_first_idx;
        }));
        foreach ($entry_author_keys[$fixed_first_idx] as $author_key) {
            $cooldown[$author_key] = $min_tracks_between_same_botbr + 1;
        }
    }

    if ($fixed_last_entry_id > 0 && count($entries) > 1) {
        $fixed_last_idx = find_entry_index_by_id($entries, $fixed_last_entry_id);
        if ($fixed_last_idx === null) {
            throw new RuntimeException("Unable to enforce last-track anchor: entry {$fixed_last_entry_id} not found.");
        }
        if (in_array($fixed_last_idx, $prefix_indices, true)) {
            throw new RuntimeException("Unable to enforce anchors: first and last anchors refer to the same track.");
        }
        $required_last_idx = $fixed_last_idx;
    }

    $memo = [];
    $calls = 0;
    $max_calls = 300000;
    $tail_indices = solve_spaced_order_indices(
        $entry_author_keys,
        $remaining_indices,
        $cooldown,
        $min_tracks_between_same_botbr,
        $required_last_idx,
        $memo,
        $calls,
        $max_calls
    );

    if (!is_array($tail_indices)) {
        throw new RuntimeException(
            "Unable to satisfy final track spacing rule: each botbr must have at least {$min_tracks_between_same_botbr} tracks between appearances."
        );
    }

    $solution_indices = array_merge($prefix_indices, $tail_indices);
    $result = [];
    foreach ($solution_indices as $idx) {
        $result[] = $entries[$idx];
    }
    return $result;
}

function solve_spaced_order_indices(
    array $entry_author_keys,
    array $remaining_indices,
    array $cooldown,
    int $min_tracks_between_same_botbr,
    ?int $required_last_idx,
    array &$memo,
    int &$calls,
    int $max_calls
): ?array {
    $calls++;
    if ($calls > $max_calls) {
        return null;
    }

    if (count($remaining_indices) === 0) {
        return [];
    }

    $cooldown = decrement_and_normalize_cooldown($cooldown);
    $state_key = build_spacing_state_key($remaining_indices, $cooldown, $required_last_idx);
    if (isset($memo[$state_key])) {
        return null;
    }

    $candidates = [];
    foreach ($remaining_indices as $idx) {
        $blocked = false;
        foreach ($entry_author_keys[$idx] as $author_key) {
            if (isset($cooldown[$author_key]) && $cooldown[$author_key] > 0) {
                $blocked = true;
                break;
            }
        }
        if (!$blocked) {
            $candidates[] = $idx;
        }
    }

    if (count($candidates) === 0) {
        $memo[$state_key] = true;
        return null;
    }

    if ($required_last_idx !== null) {
        if (!in_array($required_last_idx, $remaining_indices, true)) {
            $memo[$state_key] = true;
            return null;
        }
        if (count($remaining_indices) > 1) {
            $candidates = array_values(array_filter($candidates, static function ($idx) use ($required_last_idx) {
                return $idx !== $required_last_idx;
            }));
            if (count($candidates) === 0) {
                $memo[$state_key] = true;
                return null;
            }
        } elseif ($remaining_indices[0] !== $required_last_idx) {
            $memo[$state_key] = true;
            return null;
        }
    }

    $author_remaining_counts = [];
    foreach ($remaining_indices as $idx) {
        foreach ($entry_author_keys[$idx] as $author_key) {
            $author_remaining_counts[$author_key] = ($author_remaining_counts[$author_key] ?? 0) + 1;
        }
    }

    usort($candidates, static function ($a, $b) use ($entry_author_keys, $author_remaining_counts): int {
        $a_keys = $entry_author_keys[$a];
        $b_keys = $entry_author_keys[$b];
        $a_collab = count($a_keys);
        $b_collab = count($b_keys);
        if ($a_collab !== $b_collab) {
            return $b_collab <=> $a_collab; // place collaborations first
        }

        $a_pressure = 0;
        foreach ($a_keys as $key) {
            $a_pressure += $author_remaining_counts[$key] ?? 0;
        }
        $b_pressure = 0;
        foreach ($b_keys as $key) {
            $b_pressure += $author_remaining_counts[$key] ?? 0;
        }
        if ($a_pressure !== $b_pressure) {
            return $b_pressure <=> $a_pressure; // place high-pressure options first
        }

        return $a <=> $b; // preserve base order as tiebreak
    });

    foreach ($candidates as $chosen_idx) {
        $next_remaining_indices = [];
        foreach ($remaining_indices as $idx) {
            if ($idx !== $chosen_idx) {
                $next_remaining_indices[] = $idx;
            }
        }

        $next_cooldown = $cooldown;
        foreach ($entry_author_keys[$chosen_idx] as $author_key) {
            $next_cooldown[$author_key] = $min_tracks_between_same_botbr + 1;
        }

        $tail = solve_spaced_order_indices(
            $entry_author_keys,
            $next_remaining_indices,
            $next_cooldown,
            $min_tracks_between_same_botbr,
            $required_last_idx,
            $memo,
            $calls,
            $max_calls
        );
        if (is_array($tail)) {
            array_unshift($tail, $chosen_idx);
            return $tail;
        }
    }

    $memo[$state_key] = true;
    return null;
}

function decrement_and_normalize_cooldown(array $cooldown): array
{
    $next = [];
    foreach ($cooldown as $key => $value) {
        $value = (int)$value - 1;
        if ($value > 0) {
            $next[$key] = $value;
        }
    }
    ksort($next);
    return $next;
}

function build_spacing_state_key(array $remaining_indices, array $cooldown, ?int $required_last_idx): string
{
    $remaining_part = implode(',', $remaining_indices);
    $cooldown_parts = [];
    foreach ($cooldown as $key => $value) {
        $cooldown_parts[] = $key . ':' . $value;
    }
    $required_last_part = ($required_last_idx === null) ? 'none' : (string)$required_last_idx;
    return $remaining_part . '|' . implode(',', $cooldown_parts) . '|last:' . $required_last_part;
}

function find_entry_index_by_id(array $entries, int $entry_id): ?int
{
    foreach ($entries as $idx => $entry) {
        if ((int)get_value($entry, ['id'], 0) === $entry_id) {
            return $idx;
        }
    }
    return null;
}

function enforce_anchor_positions(array $ordered_entries, int $fixed_first_entry_id, int $fixed_last_entry_id): array
{
    $entries = array_values($ordered_entries);
    if ($fixed_first_entry_id > 0) {
        $first_idx = find_entry_index_by_id($entries, $fixed_first_entry_id);
        if ($first_idx !== null && $first_idx !== 0) {
            $first_entry = $entries[$first_idx];
            array_splice($entries, $first_idx, 1);
            array_unshift($entries, $first_entry);
        }
    }
    if ($fixed_last_entry_id > 0 && count($entries) > 1) {
        $last_idx = find_entry_index_by_id($entries, $fixed_last_entry_id);
        if ($last_idx !== null && $last_idx !== (count($entries) - 1)) {
            $last_entry = $entries[$last_idx];
            array_splice($entries, $last_idx, 1);
            $entries[] = $last_entry;
        }
    }
    return $entries;
}

function prompt_desired_track_count(int $current_count, int $total_possible): int
{
    $message = "Current selected track count is {$current_count}. ";
    $message .= "Total possible tracks are {$total_possible}. ";
    $message .= "Enter desired track count [{$current_count}-{$total_possible}] (blank keeps {$current_count}): ";

    while (true) {
        fwrite(STDOUT, $message);
        $input = function_exists('readline') ? readline() : fgets(STDIN);
        if (!is_string($input)) {
            return $current_count;
        }

        $input = trim($input);
        if ($input === '') {
            return $current_count;
        }
        if (!ctype_digit($input)) {
            fwrite(STDOUT, "Please enter a number.\n");
            continue;
        }

        $desired = (int)$input;
        if ($desired < $current_count || $desired > $total_possible) {
            fwrite(STDOUT, "Please choose a value between {$current_count} and {$total_possible}.\n");
            continue;
        }
        return $desired;
    }
}

function expand_selection_to_count(array $selected, array $filtered_by_score_desc, int $desired_count, int $max_per_botbr): array
{
    $selected_map = [];
    $botbr_counts = [];
    foreach ($selected as $entry) {
        $id = (int)get_value($entry, ['id'], 0);
        if ($id > 0) {
            $selected_map[$id] = $entry;
            foreach (get_botbr_keys($entry) as $botbr_key) {
                $botbr_counts[$botbr_key] = ($botbr_counts[$botbr_key] ?? 0) + 1;
            }
        }
    }

    foreach ($filtered_by_score_desc as $entry) {
        if (count($selected_map) >= $desired_count) {
            break;
        }
        $id = (int)get_value($entry, ['id'], 0);
        if ($id <= 0 || isset($selected_map[$id])) {
            continue;
        }

        $candidate_keys = get_botbr_keys($entry);
        $blocked = false;
        foreach ($candidate_keys as $botbr_key) {
            $botbr_count = $botbr_counts[$botbr_key] ?? 0;
            if ($botbr_count >= $max_per_botbr) {
                $blocked = true;
                break;
            }
        }
        if ($blocked) {
            continue;
        }

        $selected_map[$id] = $entry;
        foreach ($candidate_keys as $botbr_key) {
            $botbr_counts[$botbr_key] = ($botbr_counts[$botbr_key] ?? 0) + 1;
        }
    }

    return array_values($selected_map);
}

function get_author_objects(array $entry): array
{
    if (isset($entry['authors']) && is_array($entry['authors']) && count($entry['authors']) > 0) {
        return $entry['authors'];
    }
    if (isset($entry['botbr']) && is_array($entry['botbr'])) {
        return [$entry['botbr']];
    }
    return [];
}

function get_author_names_display(array $entry): string
{
    $authors = get_author_objects($entry);
    $names = [];
    foreach ($authors as $author) {
        $name = trim((string)($author['name'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }
    if (count($names) === 0) {
        return 'unknown_botbr';
    }
    return implode(' & ', $names);
}

function get_botbr_keys(array $entry): array
{
    $authors = get_author_objects($entry);
    $keys = [];
    foreach ($authors as $author) {
        if (isset($author['id']) && $author['id'] !== '') {
            $keys[] = 'author:' . (string)$author['id'];
            continue;
        }
        $name = trim((string)($author['name'] ?? ''));
        if ($name !== '') {
            $keys[] = 'name:' . $name;
        }
    }
    $keys = array_values(array_unique($keys));
    if (count($keys) === 0) {
        $keys[] = 'name:unknown_botbr';
    }
    return $keys;
}

function get_bucket_key(array $entry): string
{
    $format_token = (string)get_value($entry, ['format_token', 'format.token'], 'unknown');
    $period_id = (string)get_value($entry, ['period_id', 'period.id'], '0');
    return $format_token . '|' . $period_id;
}

function reindex_entries_by_id(array $entries): array
{
    $out = [];
    foreach ($entries as $entry) {
        $id = (int)get_value($entry, ['id'], 0);
        if ($id > 0) {
            $out[$id] = $entry;
        }
    }
    return $out;
}

function apply_botbr_cap_to_selection(array $entries, int $max_per_botbr): array
{
    usort($entries, 'compare_by_score_desc');
    $counts = [];
    $kept = [];
    foreach ($entries as $entry) {
        $entry_keys = get_botbr_keys($entry);
        $blocked = false;
        foreach ($entry_keys as $botbr_key) {
            $current = $counts[$botbr_key] ?? 0;
            if ($current >= $max_per_botbr) {
                $blocked = true;
                break;
            }
        }
        if ($blocked) {
            continue;
        }
        foreach ($entry_keys as $botbr_key) {
            $counts[$botbr_key] = ($counts[$botbr_key] ?? 0) + 1;
        }
        $kept[] = $entry;
    }
    return $kept;
}

function build_selected_with_dynamic_top_bucket(array $filtered, array $buckets, int $per_bucket_max, int $max_per_botbr): array
{
    $top_percent_base_count = (int)ceil(count($filtered) / 4);
    $top_percent_bonus_count = 0;
    $selected = [];
    $top_percent_count = $top_percent_base_count;

    for ($iter = 0; $iter < 20; $iter++) {
        $top_percent_count = min(count($filtered), $top_percent_base_count + $top_percent_bonus_count);
        $selected_map = [];

        foreach ($buckets as $bucket_entries) {
            usort($bucket_entries, 'compare_by_score_desc');
            $top_from_bucket = array_slice($bucket_entries, 0, $per_bucket_max);
            foreach ($top_from_bucket as $entry) {
                $id = (int)get_value($entry, ['id'], 0);
                if ($id > 0) {
                    $selected_map[$id] = $entry;
                }
            }
        }

        $top_overall = array_slice($filtered, 0, $top_percent_count);
        foreach ($top_overall as $entry) {
            $id = (int)get_value($entry, ['id'], 0);
            if ($id > 0) {
                $selected_map[$id] = $entry;
            }
        }

        $selected = array_values($selected_map);
        usort($selected, 'compare_by_score_desc');
        $selected = apply_botbr_cap_to_selection($selected, $max_per_botbr);
        $selected_map = reindex_entries_by_id($selected);
        $selected_map = refill_bucket_selections($selected_map, $buckets, $per_bucket_max, $max_per_botbr);
        $selected = array_values($selected_map);
        usort($selected, 'compare_by_score_desc');

        $botbr_counts = [];
        foreach ($selected as $entry) {
            foreach (get_botbr_keys($entry) as $botbr_key) {
                $botbr_counts[$botbr_key] = ($botbr_counts[$botbr_key] ?? 0) + 1;
            }
        }

        $next_bonus = 0;
        foreach ($botbr_counts as $count) {
            if ($count > 3) {
                $next_bonus += ($count - 3);
            }
        }

        if ($next_bonus === $top_percent_bonus_count) {
            break;
        }
        $top_percent_bonus_count = $next_bonus;
    }

    return [
        'selected' => $selected,
        'top_percent_count' => $top_percent_count,
        'top_percent_base_count' => $top_percent_base_count,
        'top_percent_bonus_count' => $top_percent_bonus_count,
    ];
}

function refill_bucket_selections(array $selected_map, array $buckets, int $per_bucket_max, int $per_botbr_max): array
{
    $botbr_counts = [];
    foreach ($selected_map as $entry) {
        foreach (get_botbr_keys($entry) as $botbr_key) {
            $botbr_counts[$botbr_key] = ($botbr_counts[$botbr_key] ?? 0) + 1;
        }
    }

    foreach ($buckets as $bucket_entries) {
        usort($bucket_entries, 'compare_by_score_desc');
        $target_count = min($per_bucket_max, count($bucket_entries));
        if ($target_count === 0) {
            continue;
        }
        $bucket_key = get_bucket_key($bucket_entries[0]);
        $selected_in_bucket = 0;

        foreach ($selected_map as $selected_entry) {
            if (get_bucket_key($selected_entry) === $bucket_key) {
                $selected_in_bucket++;
            }
        }

        if ($selected_in_bucket >= $target_count) {
            continue;
        }

        foreach ($bucket_entries as $candidate) {
            if ($selected_in_bucket >= $target_count) {
                break;
            }

            $candidate_id = (int)get_value($candidate, ['id'], 0);
            if ($candidate_id <= 0 || isset($selected_map[$candidate_id])) {
                continue;
            }

            $candidate_keys = get_botbr_keys($candidate);
            $blocked = false;
            foreach ($candidate_keys as $botbr_key) {
                $current_botbr_count = $botbr_counts[$botbr_key] ?? 0;
                if ($current_botbr_count >= $per_botbr_max) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) {
                continue;
            }

            $selected_map[$candidate_id] = $candidate;
            foreach ($candidate_keys as $botbr_key) {
                $botbr_counts[$botbr_key] = ($botbr_counts[$botbr_key] ?? 0) + 1;
            }
            $selected_in_bucket++;
        }
    }

    return $selected_map;
}

function sanitize_file_component(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[\\\\\\/\\:\\*\\?"<>\\|]+/', '_', $value);
    $value = preg_replace('/\\s+/', ' ', $value);
    return trim((string)$value, " .\t\n\r\0\x0B");
}

function build_output_structure(string $root, bool $preview_only = false): array
{
    if ($preview_only) {
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException("Failed to create directory: {$root}");
        }
        return ['root' => $root];
    }

    $dirs = [
        'root' => $root,
        'bandcamp' => $root . DIRECTORY_SEPARATOR . 'bandcamp',
        'youtube' => $root . DIRECTORY_SEPARATOR . 'youtube',
        'youtube_tracks' => $root . DIRECTORY_SEPARATOR . 'youtube' . DIRECTORY_SEPARATOR . 'tracks',
        'youtube_normalized' => $root . DIRECTORY_SEPARATOR . 'youtube' . DIRECTORY_SEPARATOR . 'normalized',
        'temp' => $root . DIRECTORY_SEPARATOR . 'temp',
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }
    }
    return $dirs;
}

function build_preflight_log(
    array $filtered,
    array $buckets,
    int $top_percent_count,
    int $top_percent_base_count,
    int $top_percent_bonus_count,
    array $selected,
    array $ordered,
    array $track_filenames,
    int $projected_total_seconds,
    string $spacing_status,
    array $spacing_warning_lines,
    string $release_date = '',
    ?int $total_processing_seconds = null
): string
{
    $log_lines = [];
    if ($release_date !== '') {
        $log_lines[] = "Release date: {$release_date}";
    }
    $log_lines[] = "Filtered entries (audio + non-bit): " . count($filtered);
    $log_lines[] = "Unique (format_token, period_id) buckets: " . count($buckets);
    $log_lines[] = "Top 25% base count (ceil): {$top_percent_base_count}";
    $log_lines[] = "Top 25% bonus count (tracks beyond botbr top 3): {$top_percent_bonus_count}";
    $log_lines[] = "Top 25% effective count: {$top_percent_count}";
    $log_lines[] = "Selected unique entries: " . count($selected);
    $log_lines[] = "Final ordered tracks: " . count($ordered);
    $log_lines[] = "Projected total runtime: " . format_seconds_hhmmss($projected_total_seconds);
    $log_lines[] = "Spacing rule status: {$spacing_status}";
    if ($spacing_status !== 'applied') {
        $log_lines[] = "Spacing rule conflicts:";
        foreach ($spacing_warning_lines as $line) {
            $log_lines[] = $line;
        }
    }
    if ($total_processing_seconds !== null) {
        $log_lines[] = "Total processing time: " . format_seconds_hhmmss($total_processing_seconds);
    }
    $log_lines[] = "";
    $log_lines[] = "Track list:";
    foreach ($track_filenames as $track_filename) {
        $log_lines[] = $track_filename;
    }
    return implode(PHP_EOL, $log_lines) . PHP_EOL;
}

function format_seconds_hhmmss(int $total_seconds): string
{
    if ($total_seconds < 0) {
        $total_seconds = 0;
    }
    $hours = intdiv($total_seconds, 3600);
    $minutes = intdiv($total_seconds % 3600, 60);
    $seconds = $total_seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function format_seconds_compact_mmss_or_hhmmss(int $total_seconds): string
{
    if ($total_seconds < 0) {
        $total_seconds = 0;
    }
    $hours = intdiv($total_seconds, 3600);
    $minutes = intdiv($total_seconds % 3600, 60);
    $seconds = $total_seconds % 60;
    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
    return sprintf('%02d:%02d', $minutes, $seconds);
}

function format_battle_release_date_mmddyyyy(array $battle): string
{
    $ts = resolve_battle_release_timestamp($battle);
    if ($ts === null) {
        return '';
    }
    return date('m/d/Y', $ts);
}

function format_battle_release_date_long(array $battle): string
{
    $ts = resolve_battle_release_timestamp($battle);
    if ($ts === null) {
        return '';
    }
    return date('F j, Y', $ts);
}

function resolve_battle_release_timestamp(array $battle): ?int
{
    $candidates = [
        'datetime_end',
        'end_datetime',
        'date_end',
        'end_date',
        'ends_at',
        'end',
    ];
    foreach ($candidates as $key) {
        $value = trim((string)get_value($battle, [$key], ''));
        if ($value === '') {
            continue;
        }
        $ts = strtotime($value);
        if ($ts !== false) {
            return (int)$ts;
        }
    }
    return null;
}

function build_track_title_display(string $title, string $format_token, bool $include_format): string
{
    if (!$include_format) {
        return $title;
    }
    return "{$title} [{$format_token}]";
}

function build_format_legend_lines(array $ordered_entries, array $format_title_map = []): array
{
    $legend = [];
    foreach ($ordered_entries as $entry) {
        $token = (string)get_value($entry, ['format_token', 'format.token'], 'unknown');
        if ($token === '') {
            $token = 'unknown';
        }
        if (isset($legend[$token])) {
            continue;
        }
        $title = trim((string)get_value($entry, ['format.title', 'format.name', 'format_title', 'format_name'], ''));
        if ($title === '' && isset($format_title_map[$token])) {
            $title = trim((string)$format_title_map[$token]);
        }
        if ($title === '') {
            $title = $token;
        }
        $legend[$token] = "{$token} -> {$title}";
    }
    ksort($legend, SORT_NATURAL | SORT_FLAG_CASE);
    return array_values($legend);
}

function html_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function build_copy_links_html(string $battle_title, string $release_date, array $manifest_entries, array $format_legend_lines): string
{
    $lines = [];
    $lines[] = '<!doctype html>';
    $lines[] = '<html lang="en">';
    $lines[] = '<head>';
    $lines[] = '  <meta charset="utf-8">';
    $lines[] = '  <meta name="viewport" content="width=device-width, initial-scale=1">';
    $lines[] = '  <title>Compilation Copy Helper</title>';
    $lines[] = '  <style>';
    $lines[] = '    body { font-family: Arial, sans-serif; margin: 24px; line-height: 1.5; }';
    $lines[] = '    h1, h2 { margin: 0 0 12px; }';
    $lines[] = '    h2 { margin-top: 24px; }';
    $lines[] = '    ul { margin: 0; padding-left: 20px; }';
    $lines[] = '    li { margin: 4px 0; }';
    $lines[] = '    a { text-decoration: none; }';
    $lines[] = '    a:hover { text-decoration: underline; }';
    $lines[] = '    .legend-block { display: inline-block; white-space: pre-line; }';
    $lines[] = '    #copy-status { margin: 0 0 16px; color: #1f6f3f; min-height: 1.2em; }';
    $lines[] = '  </style>';
    $lines[] = '</head>';
    $lines[] = '<body>';
    $lines[] = '  <h1>Copy Helper</h1>';
    $lines[] = '  <div id="copy-status" aria-live="polite"></div>';
    $lines[] = '  <h2>Battle Title</h2>';
    $lines[] = '  <ul>';
    $lines[] = '    <li><a class="copy-link" href="#" data-copy="' . html_escape($battle_title) . '">' . html_escape($battle_title) . '</a></li>';
    $lines[] = '  </ul>';
    if ($release_date !== '') {
        $lines[] = '  <h2>Release Date</h2>';
        $lines[] = '  <ul>';
        $lines[] = '    <li><a class="copy-link" href="#" data-copy="' . html_escape($release_date) . '">' . html_escape($release_date) . '</a></li>';
        $lines[] = '  </ul>';
    }
    $lines[] = '  <h2>Tracks</h2>';
    $lines[] = '  <ul>';
    foreach ($manifest_entries as $entry) {
        $title = (string)($entry['title_display'] ?? '');
        $author = (string)($entry['botbr'] ?? '');
        $track_position = (int)($entry['track'] ?? 0);
        $lines[] = '    <li><span>' . html_escape((string)$track_position) . '</span> <a class="copy-link" href="#" data-copy="' . html_escape($title) . '">' . html_escape($title) . '</a> - <a class="copy-link" href="#" data-copy="' . html_escape($author) . '">' . html_escape($author) . '</a></li>';
    }
    $lines[] = '  </ul>';
    $lines[] = '  <h2>Format Legend</h2>';
    $legend_text = implode("\n", array_map(static function ($line): string {
        return (string)$line;
    }, $format_legend_lines));
    $lines[] = '  <a class="copy-link legend-block" href="#" data-copy="' . html_escape($legend_text) . '">' . html_escape($legend_text) . '</a>';
    $lines[] = '  <script>';
    $lines[] = '    var statusNode = document.getElementById("copy-status");';
    $lines[] = '    function showCopied(text) {';
    $lines[] = '      if (!statusNode) { return; }';
    $lines[] = '      statusNode.textContent = "Copied: " + text;';
    $lines[] = '      window.clearTimeout(showCopied._timer || 0);';
    $lines[] = '      showCopied._timer = window.setTimeout(function () {';
    $lines[] = '        statusNode.textContent = "";';
    $lines[] = '      }, 1500);';
    $lines[] = '    }';
    $lines[] = '    function fallbackCopy(text) {';
    $lines[] = '      var ta = document.createElement("textarea");';
    $lines[] = '      ta.value = text;';
    $lines[] = '      ta.setAttribute("readonly", "readonly");';
    $lines[] = '      ta.style.position = "fixed";';
    $lines[] = '      ta.style.left = "-9999px";';
    $lines[] = '      document.body.appendChild(ta);';
    $lines[] = '      ta.select();';
    $lines[] = '      try { document.execCommand("copy"); } catch (e) {}';
    $lines[] = '      document.body.removeChild(ta);';
    $lines[] = '    }';
    $lines[] = '    document.querySelectorAll(".copy-link").forEach(function (node) {';
    $lines[] = '      node.addEventListener("click", function (event) {';
    $lines[] = '        event.preventDefault();';
    $lines[] = '        var text = node.getAttribute("data-copy") || "";';
    $lines[] = '        if (navigator.clipboard && navigator.clipboard.writeText) {';
    $lines[] = '          navigator.clipboard.writeText(text).then(function () {';
    $lines[] = '            showCopied(text);';
    $lines[] = '          }).catch(function () {';
    $lines[] = '            fallbackCopy(text);';
    $lines[] = '            showCopied(text);';
    $lines[] = '          });';
    $lines[] = '        } else {';
    $lines[] = '          fallbackCopy(text);';
    $lines[] = '          showCopied(text);';
    $lines[] = '        }';
    $lines[] = '      });';
    $lines[] = '    });';
    $lines[] = '  </script>';
    $lines[] = '</body>';
    $lines[] = '</html>';
    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function prompt_continue_without_spacing(string $message, array $summary_lines): bool
{
    fwrite(STDOUT, "\nWARNING: {$message}\n");
    foreach ($summary_lines as $line) {
        fwrite(STDOUT, $line . "\n");
    }
    while (true) {
        fwrite(STDOUT, "Continue with non-compliant track order? [y/N]: ");
        $input = function_exists('readline') ? readline() : fgets(STDIN);
        if (!is_string($input)) {
            return false;
        }
        $input = strtolower(trim($input));
        if ($input === '' || $input === 'n' || $input === 'no') {
            return false;
        }
        if ($input === 'y' || $input === 'yes') {
            return true;
        }
        fwrite(STDOUT, "Please answer y or n.\n");
    }
}

function build_spacing_violation_summary_lines(array $ordered_entries, int $min_tracks_between_same_botbr, int $max_lines): array
{
    $lines = [];
    $last_positions = [];
    $author_name_by_key = get_author_name_by_key($ordered_entries);
    foreach ($ordered_entries as $idx => $entry) {
        $track_no = $idx + 1;
        foreach (get_botbr_keys($entry) as $botbr_key) {
            if (isset($last_positions[$botbr_key])) {
                $prev_track_no = $last_positions[$botbr_key] + 1;
                $between = $idx - $last_positions[$botbr_key] - 1;
                if ($between < $min_tracks_between_same_botbr) {
                    $author_label = $author_name_by_key[$botbr_key] ?? $botbr_key;
                    $lines[] = " - {$author_label}: track {$prev_track_no} and track {$track_no} only have {$between} tracks between";
                    if (count($lines) >= $max_lines) {
                        return $lines;
                    }
                }
            }
            $last_positions[$botbr_key] = $idx;
        }
    }
    if (count($lines) === 0) {
        $lines[] = " - No detailed spacing conflicts found in summary.";
    }
    return $lines;
}

function get_author_name_by_key(array $entries): array
{
    $map = [];
    foreach ($entries as $entry) {
        foreach (get_author_objects($entry) as $author) {
            if (isset($author['id']) && $author['id'] !== '') {
                $map['author:' . (string)$author['id']] = (string)($author['name'] ?? ('author_' . (string)$author['id']));
            } else {
                $name = trim((string)($author['name'] ?? ''));
                if ($name !== '') {
                    $map['name:' . $name] = $name;
                }
            }
        }
    }
    return $map;
}

function prompt_overwrite_directory(string $dir): bool
{
    $message = "Output directory already exists: {$dir}. Overwrite? [y/N]: ";
    while (true) {
        fwrite(STDOUT, $message);
        $input = function_exists('readline') ? readline() : fgets(STDIN);
        if (!is_string($input)) {
            return false;
        }
        $input = strtolower(trim($input));
        if ($input === '' || $input === 'n' || $input === 'no') {
            return false;
        }
        if ($input === 'y' || $input === 'yes') {
            return true;
        }
        fwrite(STDOUT, "Please answer y or n.\n");
    }
}

function remove_directory_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if (!is_array($items)) {
        throw new RuntimeException("Failed to read directory for overwrite: {$dir}");
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            remove_directory_recursive($path);
        } else {
            if (!@unlink($path)) {
                throw new RuntimeException("Failed to remove file during overwrite: {$path}");
            }
        }
    }
    if (!@rmdir($dir)) {
        throw new RuntimeException("Failed to remove directory during overwrite: {$dir}");
    }
}

function detect_extension_from_url(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return 'png';
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === '' || strlen($ext) > 5) {
        return 'png';
    }
    return preg_replace('/[^a-z0-9]/', '', $ext) ?: 'png';
}

function encode_url_path(string $url): string
{
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return $url;
    }

    $path = $parts['path'] ?? '';
    $segments = explode('/', $path);
    foreach ($segments as $i => $segment) {
        if ($segment === '') {
            continue;
        }
        $segments[$i] = rawurlencode(rawurldecode($segment));
    }
    $encoded_path = implode('/', $segments);

    $rebuilt = $parts['scheme'] . '://';
    if (isset($parts['user'])) {
        $rebuilt .= $parts['user'];
        if (isset($parts['pass'])) {
            $rebuilt .= ':' . $parts['pass'];
        }
        $rebuilt .= '@';
    }
    $rebuilt .= $parts['host'];
    if (isset($parts['port'])) {
        $rebuilt .= ':' . $parts['port'];
    }
    $rebuilt .= $encoded_path;
    if (isset($parts['query'])) {
        $rebuilt .= '?' . $parts['query'];
    }
    if (isset($parts['fragment'])) {
        $rebuilt .= '#' . $parts['fragment'];
    }
    return $rebuilt;
}

function get_image_dimensions(string $path): array
{
    $dims = @getimagesize($path);
    if (!is_array($dims) || !isset($dims[0], $dims[1])) {
        throw new RuntimeException("Unable to detect image dimensions: {$path}");
    }
    return [(int)$dims[0], (int)$dims[1]];
}

function create_tiled_image_background(string $pattern_path, string $output_path, int $target_w, int $target_h): void
{
    $pattern = @imagecreatefrompng($pattern_path);
    if ($pattern === false) {
        throw new RuntimeException("Failed to load tile pattern image: {$pattern_path}");
    }
    $tile_w = imagesx($pattern);
    $tile_h = imagesy($pattern);
    if ($tile_w <= 0 || $tile_h <= 0) {
        imagedestroy($pattern);
        throw new RuntimeException("Invalid tile pattern dimensions: {$pattern_path}");
    }
    $canvas = imagecreatetruecolor($target_w, $target_h);
    if ($canvas === false) {
        imagedestroy($pattern);
        throw new RuntimeException("Failed to create thumbnail background canvas.");
    }
    for ($y = 0; $y < $target_h; $y += $tile_h) {
        for ($x = 0; $x < $target_w; $x += $tile_w) {
            imagecopy($canvas, $pattern, $x, $y, 0, 0, $tile_w, $tile_h);
        }
    }
    if (!imagepng($canvas, $output_path)) {
        imagedestroy($canvas);
        imagedestroy($pattern);
        throw new RuntimeException("Failed to write tiled thumbnail background: {$output_path}");
    }
    imagedestroy($canvas);
    imagedestroy($pattern);
}

function rename_or_fail(string $from, string $to, string $context): void
{
    if (!@rename($from, $to)) {
        throw new RuntimeException("Failed to {$context}: {$from} -> {$to}");
    }
}

function copy_or_fail(string $from, string $to, string $context): void
{
    if (!@copy($from, $to)) {
        throw new RuntimeException("Failed to {$context}: {$from} -> {$to}");
    }
}

function run(string $command, string $context): void
{
    echo "[RUN] {$context}\n";
    system($command, $exit_code);
    if ($exit_code !== 0) {
        throw new RuntimeException("Command failed ({$context}): {$command}");
    }
}

function esc(string $arg): string
{
    return escapeshellarg($arg);
}

function normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function resolve_absolute_path(string $path): string
{
    $real = realpath($path);
    if ($real === false) {
        return normalize_path($path);
    }
    return normalize_path($real);
}

function escape_concat_path(string $path): string
{
    return str_replace("'", "'\\''", $path);
}

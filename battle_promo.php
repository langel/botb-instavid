#!/usr/bin/env php
<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if ($argc < 2 || !ctype_digit((string)$argv[1])) {
    fwrite(STDERR, "Usage: php battle_promo.php <battle_id>\n");
    exit(1);
}

$battle_id = (int)$argv[1];
if ($battle_id <= 0) {
    fwrite(STDERR, "A valid numeric battle_id is required.\n");
    exit(1);
}

$config = parse_ini_file('config.ini', true);
if ($config === false) {
    fwrite(STDERR, "Missing or invalid config.ini\n");
    exit(1);
}

$system_type = $config['global']['system'] ?? '';
$convert_cmd = (strtolower($system_type) === 'windows') ? 'magick' : 'convert';
$identify_cmd = (strtolower($system_type) === 'windows') ? 'magick identify' : 'identify';
$user_id = $config['cookie']['user_id'] ?? '';
$serial = $config['cookie']['serial'] ?? '';
$botbr_id = $config['cookie']['botbr_id'] ?? '';
if ($user_id === '' || $serial === '' || $botbr_id === '') {
    fwrite(STDERR, "config.ini cookie values are required: user_id, serial, botbr_id\n");
    exit(1);
}

$cookie_header = "Cookie: user_id={$user_id}; serial={$serial}; botbr_id={$botbr_id}";
$api_base = 'https://battleofthebits.com/api/v1';
$site_base = 'https://battleofthebits.com';

try {
    $battle = fetch_json("{$api_base}/battle/load/{$battle_id}", $cookie_header);
    $battle_title = trim((string)($battle['title'] ?? ''));
    if ($battle_title === '') {
        throw new RuntimeException("Battle title is missing for battle_id {$battle_id}");
    }

    $promo_dir = 'promo';
    $temp_dir = $promo_dir . DIRECTORY_SEPARATOR . 'temp';
    ensure_directory($promo_dir);
    ensure_directory($temp_dir);

    $pattern_path = $temp_dir . DIRECTORY_SEPARATOR . 'botb_bg.png';
    run("curl -ksSL --header " . esc($cookie_header) . " " . esc('https://battleofthebits.com/disk/debris/botb_bg.png') . " -o " . esc($pattern_path), "download BotB background tile pattern");

    $horizontal_out = $promo_dir . DIRECTORY_SEPARATOR . 'promo_hd_horizontal.jpg';
    $vertical_out = $promo_dir . DIRECTORY_SEPARATOR . 'promo_hd_vertical.jpg';
    $battle_has_ended = has_battle_ended($battle);

    if (!$battle_has_ended) {
        fwrite(STDOUT, "INFO: battle has not ended yet; building random entry-art collage (overrides cover_art_url).\n");
        $format_tokens = normalize_format_tokens($battle['format_tokens'] ?? []);
        if (count($format_tokens) === 0) {
            throw new RuntimeException("Battle has not ended and contains no format tokens; cannot fetch entry artwork candidates.");
        }
        $entries_by_id = [];
        foreach ($format_tokens as $format_token) {
            fwrite(STDOUT, "INFO: fetching entries for format token {$format_token}\n");
            $entries_raw = fetch_json("{$api_base}/entry/list/0/500?filters=battle_id~{$battle_id}^format_token~{$format_token}&sort=favs&desc=true", $cookie_header);
            $entries = normalize_entry_list($entries_raw);
            fwrite(STDOUT, "INFO: fetched " . count($entries) . " entries for token {$format_token}\n");
            foreach ($entries as $entry) {
                $entry_id = (int)get_value($entry, ['id'], 0);
                if ($entry_id > 0) {
                    $entries_by_id[$entry_id] = $entry;
                }
            }
        }
        $entries = array_values($entries_by_id);
        fwrite(STDOUT, "INFO: deduped to " . count($entries) . " unique entries across all tokens\n");
        $candidates = [];
        foreach ($entries as $entry) {
            $entry_id = (int)get_value($entry, ['id'], 0);
            if ($entry_id <= 0) {
                continue;
            }
            $source_url = resolve_entry_art_source_url($entry, $site_base);
            if ($source_url === '') {
                continue;
            }
            if (!is_supported_image_url($source_url)) {
                continue;
            }
            $candidates[] = [
                'entry_id' => $entry_id,
                'source_url' => $source_url,
            ];
        }
        fwrite(STDOUT, "INFO: image-capable candidate entries: " . count($candidates) . "\n");
        if (count($candidates) < 6) {
            throw new RuntimeException("Battle has not ended and only " . count($candidates) . " image candidates were found; need at least 6 to build collage.");
        }

        shuffle($candidates);
        $selected_count = min(8, count($candidates));
        $selected = array_slice($candidates, 0, $selected_count);
        $selected_ids = array_map(static function (array $candidate): int {
            return (int)$candidate['entry_id'];
        }, $selected);
        fwrite(STDOUT, "INFO: selected {$selected_count} entry IDs for collage: " . implode(', ', $selected_ids) . "\n");

        $tile_paths = [];
        foreach ($selected as $idx => $candidate) {
            $entry_id = (int)$candidate['entry_id'];
            $entry_art_url = encode_url_path($candidate['source_url']);
            $entry_art_ext = detect_extension_from_url($entry_art_url);
            $entry_art_src = $temp_dir . DIRECTORY_SEPARATOR . sprintf("tile_%02d_%d.%s", $idx + 1, $entry_id, $entry_art_ext);
            run("curl -ksSL --header " . esc($cookie_header) . " " . esc($entry_art_url) . " -o " . esc($entry_art_src), "download collage artwork for entry {$entry_id}");
            if (!is_valid_image_file($entry_art_src)) {
                fwrite(STDOUT, "WARNING: downloaded entry {$entry_id} is not a valid image, skipping.\n");
                continue;
            }
            $tile_paths[] = $entry_art_src;
        }
        if (count($tile_paths) < 6) {
            throw new RuntimeException("Only " . count($tile_paths) . " valid collage images downloaded; need at least 6.");
        }
        fwrite(STDOUT, "INFO: composing collage with " . count($tile_paths) . " images\n");
        compose_collage_promo($pattern_path, $tile_paths, $horizontal_out, 1920, 1080);
        compose_collage_promo($pattern_path, $tile_paths, $vertical_out, 1080, 1920);
    } else {
        $cover_url = trim((string)($battle['cover_art_url'] ?? ''));
        if ($cover_url !== '') {
            $cover_url = encode_url_path($cover_url);
            $cover_ext = detect_extension_from_url($cover_url);
            $cover_src = $temp_dir . DIRECTORY_SEPARATOR . "cover_source.{$cover_ext}";
            run("curl -ksSL --header " . esc($cookie_header) . " " . esc($cover_url) . " -o " . esc($cover_src), "download battle cover art");

            $cover_source_info = resolve_cover_still_source($cover_src, $temp_dir, $convert_cmd, $identify_cmd);
            $cover_still_src = $cover_source_info['still_path'];

            compose_cover_style_promo($pattern_path, $cover_still_src, $temp_dir, $horizontal_out, 1920, 1080, $convert_cmd, 'fit_height');
            compose_cover_style_promo($pattern_path, $cover_still_src, $temp_dir, $vertical_out, 1080, 1920, $convert_cmd, 'fit_inside');
        } else {
            fwrite(STDOUT, "WARNING: battle appears ended by end datetime, but cover_art_url is unset; using battle-art collage fallback.\n");
        $format_tokens = normalize_format_tokens($battle['format_tokens'] ?? []);
        $candidate_tokens = array_values(array_intersect($format_tokens, ['chipbattle_art', 'mixbattle_art']));
        if (count($candidate_tokens) === 0) {
            throw new RuntimeException("cover_art_url is unset and no chipbattle_art or mixbattle_art format token exists for this battle.");
        }

        $entries_by_id = [];
        foreach ($candidate_tokens as $token) {
            $url = "{$api_base}/entry/list/0/500?filters=battle_id~{$battle_id}^format_token~{$token}&sort=favs&desc=true";
            $entries_raw = fetch_json($url, $cookie_header);
            $entries = normalize_entry_list($entries_raw);
            foreach ($entries as $entry) {
                $entry_id = (int)get_value($entry, ['id'], 0);
                if ($entry_id > 0) {
                    $entries_by_id[$entry_id] = $entry;
                }
            }
        }

        $entries = array_values($entries_by_id);
        if (count($entries) === 0) {
            throw new RuntimeException("No battle-art entries found for chipbattle_art/mixbattle_art fallback.");
        }

        usort($entries, static function (array $a, array $b): int {
            $favs_a = (int)get_value($a, ['favs'], 0);
            $favs_b = (int)get_value($b, ['favs'], 0);
            if ($favs_a !== $favs_b) {
                return $favs_b <=> $favs_a;
            }
            $id_a = (int)get_value($a, ['id'], 0);
            $id_b = (int)get_value($b, ['id'], 0);
            return $id_a <=> $id_b;
        });

        $selected_count = min(8, count($entries));
        if ($selected_count < 6) {
            fwrite(STDOUT, "WARNING: only {$selected_count} fallback battle-art entries available; proceeding with fewer than 6.\n");
        }
        $selected = array_slice($entries, 0, $selected_count);

        $tile_paths = [];
        foreach ($selected as $idx => $entry) {
            $entry_id = (int)get_value($entry, ['id'], 0);
            $source_url = resolve_entry_art_source_url($entry, $site_base);
            if ($source_url === '') {
                continue;
            }
            $source_url = encode_url_path($source_url);
            $ext = detect_extension_from_url($source_url);
            $tile_path = $temp_dir . DIRECTORY_SEPARATOR . sprintf('tile_%02d_%d.%s', $idx + 1, $entry_id, $ext);
            run("curl -ksSL --header " . esc($cookie_header) . " " . esc($source_url) . " -o " . esc($tile_path), "download fallback battle-art image for entry {$entry_id}");
            if (file_exists($tile_path) && filesize($tile_path) > 0) {
                $tile_paths[] = $tile_path;
            }
        }

        if (count($tile_paths) === 0) {
            throw new RuntimeException("Failed to download fallback battle-art images.");
        }

        compose_collage_promo($pattern_path, $tile_paths, $horizontal_out, 1920, 1080);
        compose_collage_promo($pattern_path, $tile_paths, $vertical_out, 1080, 1920);
        }
    }

    remove_directory_recursive($temp_dir);
    echo "BATTLE PROMO COMPLETE\n";
    echo "Output directory: {$promo_dir}\n";
    echo "Horizontal: {$horizontal_out}\n";
    echo "Vertical: {$vertical_out}\n";
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

function normalize_format_tokens($tokens): array
{
    if (is_array($tokens)) {
        return array_values(array_filter(array_map('strval', $tokens), static function ($v): bool {
            return trim($v) !== '';
        }));
    }
    $single = trim((string)$tokens);
    if ($single === '') {
        return [];
    }
    return [$single];
}

function has_battle_ended(array $battle): bool
{
    $end_value = trim((string)get_value($battle, ['datetime_end', 'end', 'date_end'], ''));
    if ($end_value === '') {
        return false;
    }
    $end_timestamp = strtotime($end_value);
    if ($end_timestamp === false) {
        return false;
    }
    return $end_timestamp <= time();
}

function resolve_entry_art_source_url(array $entry, string $site_base): string
{
    $candidate = trim((string)get_value($entry, ['preview_url', 'thumbnail_url', 'render'], ''));
    if ($candidate === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $candidate)) {
        return $candidate;
    }
    if (strpos($candidate, '/') === 0) {
        return rtrim($site_base, '/') . $candidate;
    }
    return rtrim($site_base, '/') . '/' . ltrim($candidate, '/');
}

function is_supported_image_url(string $url): bool
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return false;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true);
}

function is_valid_image_file(string $path): bool
{
    $info = @getimagesize($path);
    return is_array($info) && count($info) >= 2 && (int)$info[0] > 0 && (int)$info[1] > 0;
}

function compose_cover_style_promo(
    string $pattern_path,
    string $cover_still_src,
    string $temp_dir,
    string $output_path,
    int $canvas_w,
    int $canvas_h,
    string $convert_cmd,
    string $resize_mode
): void
{
    $bg_path = $temp_dir . DIRECTORY_SEPARATOR . "bg_{$canvas_w}x{$canvas_h}.png";
    $cover_fit = $temp_dir . DIRECTORY_SEPARATOR . "cover_fit_{$canvas_w}x{$canvas_h}.png";
    create_tiled_image_background($pattern_path, $bg_path, $canvas_w, $canvas_h);
    if ($resize_mode === 'fit_inside') {
        $resize_geometry = "{$canvas_w}x{$canvas_h}";
    } else {
        $resize_geometry = "x{$canvas_h}";
    }
    run(
        "{$convert_cmd} " . esc($cover_still_src) . " -resize {$resize_geometry} " . esc($cover_fit),
        "resize cover art for {$canvas_w}x{$canvas_h} promo"
    );
    run(
        "{$convert_cmd} " . esc($bg_path) . " " . esc($cover_fit) . " -gravity center -composite -quality 92 " . esc($output_path),
        "compose {$canvas_w}x{$canvas_h} promo image"
    );
}

function compose_collage_promo(string $pattern_path, array $tile_paths, string $output_path, int $canvas_w, int $canvas_h): void
{
    $bg_temp = dirname($output_path) . DIRECTORY_SEPARATOR . "collage_bg_{$canvas_w}x{$canvas_h}.png";
    create_tiled_image_background($pattern_path, $bg_temp, $canvas_w, $canvas_h);
    $canvas = @imagecreatefrompng($bg_temp);
    if ($canvas === false) {
        throw new RuntimeException("Failed to load collage background: {$bg_temp}");
    }

    $count = count($tile_paths);
    $margin = max(20, (int)floor(min($canvas_w, $canvas_h) * 0.03));
    $gutter = max(12, (int)floor(min($canvas_w, $canvas_h) * 0.02));
    $grid = choose_best_grid($count, $canvas_w, $canvas_h, $margin, $gutter);
    if ($grid === null) {
        imagedestroy($canvas);
        throw new RuntimeException("Unable to compute collage grid for {$canvas_w}x{$canvas_h}");
    }

    $cols = $grid['cols'];
    $rows = $grid['rows'];
    $tile = $grid['tile'];
    $total_w = ($cols * $tile) + (($cols - 1) * $gutter);
    $total_h = ($rows * $tile) + (($rows - 1) * $gutter);
    $start_x = (int)floor(($canvas_w - $total_w) / 2);
    $start_y = (int)floor(($canvas_h - $total_h) / 2);

    foreach ($tile_paths as $idx => $path) {
        $row = intdiv($idx, $cols);
        $col = $idx % $cols;
        $x = $start_x + ($col * ($tile + $gutter));
        $y = $start_y + ($row * ($tile + $gutter));
        $tile_img = load_image_resource($path);
        if ($tile_img === null) {
            continue;
        }
        $square = center_crop_square($tile_img, $tile);
        imagedestroy($tile_img);
        imagecopy($canvas, $square, $x, $y, 0, 0, $tile, $tile);
        imagedestroy($square);
    }

    if (!imagejpeg($canvas, $output_path, 92)) {
        imagedestroy($canvas);
        throw new RuntimeException("Failed to write collage promo image: {$output_path}");
    }
    imagedestroy($canvas);
    @unlink($bg_temp);
}

function choose_best_grid(int $count, int $canvas_w, int $canvas_h, int $margin, int $gutter): ?array
{
    $best = null;
    for ($cols = 1; $cols <= $count; $cols++) {
        $rows = (int)ceil($count / $cols);
        $avail_w = $canvas_w - (2 * $margin) - (($cols - 1) * $gutter);
        $avail_h = $canvas_h - (2 * $margin) - (($rows - 1) * $gutter);
        if ($avail_w <= 0 || $avail_h <= 0) {
            continue;
        }
        $tile = (int)floor(min($avail_w / $cols, $avail_h / $rows));
        if ($tile <= 0) {
            continue;
        }
        $empty = ($cols * $rows) - $count;
        $score = ($tile * 1000) - $empty;
        if ($best === null || $score > $best['score']) {
            $best = [
                'cols' => $cols,
                'rows' => $rows,
                'tile' => $tile,
                'score' => $score,
            ];
        }
    }
    return $best;
}

function load_image_resource(string $path)
{
    $blob = @file_get_contents($path);
    if (!is_string($blob) || $blob === '') {
        return null;
    }
    $img = @imagecreatefromstring($blob);
    return ($img === false) ? null : $img;
}

function center_crop_square($img, int $target): GdImage
{
    $src_w = imagesx($img);
    $src_h = imagesy($img);
    $side = min($src_w, $src_h);
    $src_x = (int)floor(($src_w - $side) / 2);
    $src_y = (int)floor(($src_h - $side) / 2);
    $out = imagecreatetruecolor($target, $target);
    imagecopyresampled($out, $img, 0, 0, $src_x, $src_y, $target, $target, $side, $side);
    return $out;
}

function sanitize_file_component(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[\\\\\\/\\:\\*\\?"<>\\|]+/', '_', $value);
    $value = preg_replace('/\\s+/', ' ', $value);
    return trim((string)$value, " .\t\n\r\0\x0B");
}

function ensure_directory(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Failed to create directory: {$dir}");
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

function resolve_cover_still_source(string $cover_source_path, string $temp_dir, string $convert_cmd, string $identify_cmd): array
{
    $frame_count = detect_image_frame_count($cover_source_path, $identify_cmd);
    if ($frame_count <= 1) {
        return [
            'still_path' => $cover_source_path,
            'is_animated' => false,
        ];
    }
    $still_path = $temp_dir . DIRECTORY_SEPARATOR . 'cover_source_frame0.png';
    run(
        "{$convert_cmd} " . esc($cover_source_path . '[0]') . " " . esc($still_path),
        "extract first frame from animated cover art"
    );
    if (!file_exists($still_path)) {
        throw new RuntimeException("Failed to extract first frame from animated cover art.");
    }
    return [
        'still_path' => $still_path,
        'is_animated' => true,
    ];
}

function detect_image_frame_count(string $image_path, string $identify_cmd): int
{
    $cmd = "{$identify_cmd} -ping " . esc($image_path) . " 2>/dev/null";
    $output = shell_exec($cmd);
    if (!is_string($output) || trim($output) === '') {
        return 1;
    }
    $lines = preg_split('/\R+/', trim($output)) ?: [];
    $count = count(array_values(array_filter($lines, static function ($line): bool {
        return trim((string)$line) !== '';
    })));
    return ($count > 0) ? $count : 1;
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
        throw new RuntimeException("Failed to create promo background canvas.");
    }
    for ($y = 0; $y < $target_h; $y += $tile_h) {
        for ($x = 0; $x < $target_w; $x += $tile_w) {
            imagecopy($canvas, $pattern, $x, $y, 0, 0, $tile_w, $tile_h);
        }
    }
    if (!imagepng($canvas, $output_path)) {
        imagedestroy($canvas);
        imagedestroy($pattern);
        throw new RuntimeException("Failed to write tiled promo background: {$output_path}");
    }
    imagedestroy($canvas);
    imagedestroy($pattern);
}

function remove_directory_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if (!is_array($items)) {
        throw new RuntimeException("Failed to read directory: {$dir}");
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            remove_directory_recursive($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
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

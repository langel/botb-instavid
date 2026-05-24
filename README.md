Dependencies you'll need from whatever package manager you are stuck with: 
ffmpeg, imagemagick, php, php-gd 

Primary usage:
`./instavid.sh {entry_id} [auto|wide|vertical]` (`auto` is default; auto uses vertical only when media is under 90 seconds)

This project currently requires you to have a BotB account and to add the account data from your cookie into a config.ini.
There is an example .ini file attached in the repo. Simply copy and rename it to ``config.ini``.
Then add ``windows`` if you're using Windows, or anything else if you're not using windows.
Also add the cookie ``user_id``, ``botbr_id`` and ``serial`` from your BotB cookie (after you are logged in).

Compilation builder usage:
`php comp_builder.php {battle_id}`
`php comp_builder.php {battle_id} --preview`

`comp_builder.php` will:
- fetch battle metadata from `https://battleofthebits.com/api/v1/battle/load/{battle_id}` and create an output directory named after the battle title
- fetch battle entries from `https://battleofthebits.com/api/v1/entry/list/0/500?filters=battle_id~{battle_id}`
- include only entries where `medium == 0` and `bit == 0`
- collaboration tracks use all entries in `authors`:
  - track filenames use `author1 & author2 & ...`
  - botbr cap/bonus/spacing rules count against each listed author
- select tracks using:
  - top 3 by score for each unique `(format_token, period_id)`
  - plus top 25% (ceil) overall by score
  - cap any single botbr to at most 5 tracks (highest scores kept)
  - for each selected track beyond botbr top 3, add +1 to the top-25% bucket size (while still respecting the botbr cap)
  - if cap-based removals underfill a `(format_token, period_id)` bucket, refill that bucket from next best scores while still respecting the botbr cap
- order tracks with the custom rank parity interleave rule
- preserve final anchor positions: track 1 is highest score, final track is 2nd-highest score
- apply a final strict spacing filter so the same botbr has at least 4 tracks between appearances
  - spacing solver accounts for collaboration tracks and tries alternative arrangements before declaring failure
  - if strict spacing still cannot be satisfied, show conflict summary and prompt whether to continue with non-compliant ordering
- if the initial selected count is below 25, prompt for desired track count using:
  - current selected track count
  - total possible track count
- produce one unified preflight log (selection stats + finalized track list) and output it to both:
  - console
  - `playlist_report.txt`
- include projected total runtime in zero-padded `hh:mm:ss` using track `length` values
- `--preview` / `-p` mode only writes/prints this preflight log and exits without building media assets
- if the battle-title output directory already exists, prompt for overwrite before continuing
- generate Bandcamp files:
  - normalized WAV files at `-14 LUFS` (`loudnorm`) with sample rate normalized to `44100` Hz
  - WAV channel layout is preserved from source (mono stays mono, stereo stays stereo)
  - filename format token segment uses `[format_token]`, or `-` when all selected tracks share the same format token
  - scaled battle cover art (integer multiples until dimensions are at least `1200x1200`)
- generate YouTube files:
  - render entry videos via `instavid.sh` with forced `wide` orientation
  - normalize video audio to `48000` Hz stereo
  - compile all selected tracks into one compilation video
  - generate `youtube/thumbnail.png` (`1920x1080`) using a frame from the first normalized video as background and vertically fit cover art centered horizontally
  - generate `youtube/description.txt` with battle profile URL + timestamped track list in `(hh:)mm:ss`
  - delete per-entry intermediate videos after successful concat
- generate root-level `copy_links.html` with copy-only links for:
  - battle title
  - track titles
  - track authors
  - format legend entries (`token -> format name/title`)
- track titles in tracklists include `[format]` only when the final selection has multiple formats

Output artifacts are written under the battle title directory, including:
- `bandcamp/` (WAV tracks + cover art)
- `youtube/` (final compilation + thumbnail + description)
- `selected_entries.json`
- `tracklist.txt`
- `copy_links.html`
- `playlist_report.txt` (preflight selection summary + ordered track filenames)

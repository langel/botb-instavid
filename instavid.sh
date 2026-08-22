#!/bin/bash

set -u

entry_id=""
orientation="auto"
keep_assets=0

show_usage() {
  echo "usage: ./instavid.sh {entry_id} [auto|wide|vertical] [--keep-assets]"
}

for arg in "$@"; do
  case "$arg" in
    auto|wide|vertical)
      orientation="$arg"
      ;;
    --keep-assets)
      keep_assets=1
      ;;
    *)
      if [ -z "$entry_id" ] && [[ "$arg" =~ ^[0-9]+$ ]]; then
        entry_id="$arg"
      else
        echo "invalid argument: $arg"
        show_usage
        exit 1
      fi
      ;;
  esac
done

if [ -z "$entry_id" ]; then
  show_usage
  exit 1
fi

if [ "$orientation" != "auto" ] && [ "$orientation" != "wide" ] && [ "$orientation" != "vertical" ]; then
  echo "invalid orientation: $orientation"
  echo "expected one of: auto, wide, vertical"
  exit 1
fi

if [ "$keep_assets" -eq 1 ]; then
  mkdir -p assets
else
  if [ -d assets ]; then
    rm -rf assets
  fi
  mkdir assets
fi
curl -k https://battleofthebits.com/api/v1/entry/load/$entry_id > assets/data.json

php -f assets_get.php "$orientation"
php -f assets_create.php "$orientation"

echo -e "rendering video\n"
bash assets/ffmpeg_call

if [ "$keep_assets" -eq 1 ]; then
  echo -e "keeping assets for reuse\n"
else
  echo -e "cleaning up mess\n"
  rm -rf assets
fi

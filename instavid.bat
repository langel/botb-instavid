@echo off
set entry_id=%1
set orientation=%2
if "%orientation%"=="" set orientation=auto

if "%entry_id%"=="" (
  echo usage: instavid.bat {entry_id} [auto^|wide^|vertical]
  exit /b 1
)

if /I not "%orientation%"=="auto" if /I not "%orientation%"=="wide" if /I not "%orientation%"=="vertical" (
  echo invalid orientation: %orientation%
  echo expected one of: auto, wide, vertical
  exit /b 1
)

mkdir assets
curl -k https://battleofthebits.com/api/v1/entry/load/%entry_id% > assets/data.json

php -f assets_get.php %orientation%
php -f assets_create.php %orientation%

echo -e "rendering video\n"
bash assets/ffmpeg_call

echo -e "cleaning up mess\n"
rm -rf assets

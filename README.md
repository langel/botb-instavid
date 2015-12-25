# sloppy notes that will become greatness

instagram images are 1080x1080
videos limited to 15 seconds

Composition Size: maximum width 1080 pixels (height anything)
Frame Rate 29.96 frames per second
H.264 codec / MP4
3,500 kbps video bitrate
AAC audio codec at 44.1 kHz mono

build video from avatar and mp3

video can be static image but scrolling text would be nicer
15 x 29.96 ~= 449 frames (decimal rounded down)


HD = 1920x1080
padding margins of 10% == 108px
visible dimensions are 108, 108, 1812, 972

avatar sits @ 1312, 108, 1812, 608 (500x500)
text sits @ 108, 108, 1204, 354
battle art @ 108, 408, 408, 708  (300x300)
format icon @ 462, 462, 558, 558  (96x96 || 16*6)
format title @ 570, 462, 1258, 558
battle and time text @ 462, 608, 1258, 708
botb logo sits at y816

mp3 must be mono and only 15 seconds long


mp3info -p "%S" sample.mp3   // total time in seconds
mpgsplit input.mp3 [00:00:20-00:00:58] -o output.mp3  // new shorter track!


// input image and audio -> output x264
ffmpeg -loop 1 -i image.jpg -i audio.wav -c:v libx264 -tune stillimage -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -shortest out.mp4

-t 15
-frames 449
-framerate 29.96
-r 30000/1001  <-- correct way to 29.96?!?!?

ffmpeg -loop 1 -i b-knox.gif -i b-knox.mp3 -c:v libx264 -b:v 3500k -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -shortest out.mp4




// hard-alias resize
convert checks_5.gif -filter box -resize 6x6 checks_box+1.gif
convert b-knox-big.gif -filter box -resize 1080x0180 b-knox%50d.png

convert b-knox.gif -filter box -resize 1080x0180 b-knox-big.gif

#!/bin/bash

convert b-knox.gif -filter box -resize 1080x1080 -coalesce b-knox-big.gif
convert b-knox-big.gif b-knox%05d.png


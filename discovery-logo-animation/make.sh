#!/usr/bin/env bash
# Renders 4K (3840x2160) and 1080p masters, 30 fps, 8 s, for both stage variants.
set -euo pipefail
cd "$(dirname "$0")"
for v in green light; do
  python3 render.py "$v" 3840 | ffmpeg -loglevel error -y -f rawvideo -pix_fmt rgb24 -s 3840x2160 -r 30 -i - \
    -c:v libx264 -preset slow -crf 14 -pix_fmt yuv420p -movflags +faststart "discovery-logo-$v-4k.mp4"
  ffmpeg -loglevel error -y -i "discovery-logo-$v-4k.mp4" -vf scale=1920:1080:flags=lanczos \
    -c:v libx264 -preset slow -crf 16 -pix_fmt yuv420p -movflags +faststart "discovery-logo-$v-1080p.mp4"
  ffmpeg -loglevel error -y -sseof -0.1 -i "discovery-logo-$v-4k.mp4" -frames:v 1 "discovery-logo-$v-final-frame.png"
done

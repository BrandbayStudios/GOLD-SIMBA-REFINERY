#!/usr/bin/env bash
# Renders the 15 s intro: 4K master (3 parallel chunks) plus a 1080p copy.
set -euo pipefail
cd "$(dirname "$0")"
W=3840; H=2160; N=450; CH=3
tmp=$(mktemp -d)
for i in $(seq 0 $((CH - 1))); do
  a=$((i * N / CH)); b=$(((i + 1) * N / CH))
  python3 intro.py $W $a $b | ffmpeg -loglevel error -y -f rawvideo -pix_fmt rgb24 -s ${W}x${H} -r 30 -i - \
    -c:v libx264 -preset slow -crf 14 -pix_fmt yuv420p "$tmp/part$i.mp4" &
done
wait
for i in $(seq 0 $((CH - 1))); do echo "file '$tmp/part$i.mp4'"; done > "$tmp/list.txt"
ffmpeg -loglevel error -y -f concat -safe 0 -i "$tmp/list.txt" -c copy -movflags +faststart discovery-intro-4k.mp4
ffmpeg -loglevel error -y -i discovery-intro-4k.mp4 -vf scale=1920:1080:flags=lanczos \
  -c:v libx264 -preset slow -crf 17 -pix_fmt yuv420p -movflags +faststart discovery-intro-1080p.mp4
ffmpeg -loglevel error -y -sseof -0.1 -i discovery-intro-4k.mp4 -frames:v 1 discovery-intro-final-frame.png
rm -r "$tmp"

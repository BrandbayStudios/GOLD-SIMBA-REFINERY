#!/usr/bin/env bash
# Renders the 15 s intro: 4K master (3 parallel chunks) plus a 1080p copy.
set -euo pipefail
cd "$(dirname "$0")"
W=3840; H=2160; N=450; CH=3
tmp=$(mktemp -d)
for i in $(seq 0 $((CH - 1))); do
  a=$((i * N / CH)); b=$(((i + 1) * N / CH))
  python3 intro.py $W $a $b | ffmpeg -loglevel error -y -f rawvideo -pix_fmt rgb24 -s ${W}x${H} -r 30 -i - \
    -c:v libx264 -preset slow -crf 14 -pix_fmt yuv420p "$tmp/part$i.mp4" &  # near-lossless chunk
done
wait
for i in $(seq 0 $((CH - 1))); do echo "file '$tmp/part$i.mp4'"; done > "$tmp/list.txt"
ffmpeg -loglevel error -y -f concat -safe 0 -i "$tmp/list.txt" -c copy "$tmp/master.mp4"
# film grain makes CRF output huge (~500 MB); cap the bitrate for shareable files
ffmpeg -loglevel error -y -i "$tmp/master.mp4" -c:v libx264 -preset medium -b:v 40M -maxrate 50M -bufsize 80M \
  -pix_fmt yuv420p -movflags +faststart discovery-intro-4k.mp4
ffmpeg -loglevel error -y -i "$tmp/master.mp4" -vf scale=1920:1080:flags=lanczos -c:v libx264 -preset slow \
  -b:v 12M -maxrate 16M -bufsize 24M -pix_fmt yuv420p -movflags +faststart discovery-intro-1080p.mp4
ffmpeg -loglevel error -y -sseof -0.1 -i "$tmp/master.mp4" -frames:v 1 -vf scale=1920:1080 discovery-intro-final-frame.png
rm -r "$tmp"

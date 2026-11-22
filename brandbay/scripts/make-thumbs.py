"""Make the smaller grid copies of /media images that lib.thumb() serves.

Run from the brandbay folder after adding images: python3 scripts/make-thumbs.py
Project mockups get 800px copies, everything else 480px; images already small enough are skipped.
"""
import glob
import os

from PIL import Image

os.chdir(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'public'))
sources = (glob.glob('media/logos/*.webp') + glob.glob('media/posters/*.webp') + glob.glob('media/about/*.webp')
           + glob.glob('media/projects/*.webp') + glob.glob('media/projects/*/*.webp'))
made = 0
for src in sources:
    width = 800 if src.count('/') == 2 and src.startswith('media/projects/') else 480
    image = Image.open(src)
    if image.width <= width * 1.15:
        continue
    out = 'media/thumbs/' + src[len('media/'):]
    os.makedirs(os.path.dirname(out), exist_ok=True)
    mode = 'RGBA' if image.mode in ('RGBA', 'LA', 'P') else 'RGB'
    image.convert(mode).resize((width, round(image.height * width / image.width)), Image.LANCZOS).save(out, quality=80)
    made += 1
print(f'{made} thumbnails written to public/media/thumbs')

"""Discovery Freight & Logistics - animated logo reveal.

Every element is cut directly from the supplied logo raster (no redrawing):
the emblem square, the four arrows, and the three text lines are separated
from logo-source.png and animated as layers.

Usage:  python3 render.py [green|light] [width]
        green -> deep forest-green stage (text shown in white, reversed logo)
        light -> clean white stage (logo in its exact original colours)
Writes raw RGB frames to stdout; pipe into ffmpeg (see make.sh).
"""
import sys
from collections import deque

import numpy as np
from PIL import Image, ImageFilter

HERE = __file__.rsplit("/", 1)[0] if "/" in __file__ else "."
VARIANT = sys.argv[1] if len(sys.argv) > 1 else "green"
W = int(sys.argv[2]) if len(sys.argv) > 2 else 3840
H = W * 9 // 16
FPS, DUR = 30, 8.0
N = int(FPS * DUR)

BRAND_GREEN = np.array([0, 92, 81], np.float32) / 255  # sampled from the logo
WHITE = np.ones(3, np.float32)

# ---------------------------------------------------------------- source cut
src = np.array(Image.open(f"{HERE}/logo-source.png").convert("RGB")).astype(np.float32) / 255
ink = 1 - src[..., 0]  # green ink has R=0, paper is white
SQ = (67, 39, 165, 139)  # emblem square box (x0, y0, x1, y1), exclusive end
LOGO = (52, 39, 179, 183)  # full logo box
TEXT_ROWS = [(141, 160), (161, 173), (174, 186)]  # DISCOVERY / FREIGHT / tagline

# label the four white arrows inside the square
x0, y0, x1, y1 = SQ
white = 1 - ink[y0:y1, x0:x1]
lab = np.zeros(white.shape, int)
n = 0
for sy, sx in zip(*np.where(white > 0.5)):
    if lab[sy, sx]:
        continue
    n += 1
    lab[sy, sx] = n
    q = deque([(sy, sx)])
    while q:
        y, x = q.popleft()
        for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            yy, xx = y + dy, x + dx
            if 0 <= yy < lab.shape[0] and 0 <= xx < lab.shape[1] and not lab[yy, xx] and white[yy, xx] > 0.5:
                lab[yy, xx] = n
                q.append((yy, xx))
assert n == 4, f"expected 4 arrows, found {n}"
# give anti-aliased edge pixels to the nearest arrow
for _ in range(2):
    grow = lab.copy()
    for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1), (1, 1), (-1, -1), (1, -1), (-1, 1)):
        sh = np.roll(np.roll(lab, dy, 0), dx, 1)
        grow = np.where((grow == 0) & (sh > 0) & (white > 0.02), sh, grow)
    lab = grow

# ---------------------------------------------------------------- upscale
lx0, ly0, lx1, ly1 = LOGO
S = 0.62 * H / (ly1 - ly0)  # logo occupies 62% of frame height
OX = W / 2 - (lx0 + lx1) / 2 * S  # frame offset of source (0,0)
OY = H / 2 - (ly0 + ly1) / 2 * S


def up(a, box):
    """Upscale a source-space alpha crop and tighten its edges for crispness."""
    bx0, by0, bx1, by1 = box
    w, h = round((bx1 - bx0) * S), round((by1 - by0) * S)
    big = np.array(Image.fromarray(a.astype(np.float32), "F").resize((w, h), Image.BICUBIC))
    t = np.clip((big - 0.22) / 0.56, 0, 1)
    return (t * t * (3 - 2 * t)).astype(np.float32)


def place(box):
    return round(OX + box[0] * S), round(OY + box[1] * S)


# emblem crop in frame coordinates
ex, ey = place(SQ)
sq_w, sq_h = round((x1 - x0) * S), round((y1 - y0) * S)
arrows = [up(white * (lab == i), SQ)[:sq_h, :sq_w] for i in range(1, 5)]
# order arrows by head position (leftmost head first)
arrows.sort(key=lambda a: np.where(a > 0.5)[1].min())
texts = []
for r0, r1 in TEXT_ROWS:
    box = (lx0, r0, lx1, r1)
    texts.append((place(box), up(ink[r0:r1, lx0:lx1], box)))

# ---------------------------------------------------------------- stage
yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
rad = np.sqrt(((xx - W / 2) / W) ** 2 + ((yy - H * 0.45) / H) ** 2 * 0.8)
if VARIANT == "green":
    centre, edge = np.array([12, 66, 56]) / 255, np.array([2, 20, 17]) / 255
    TEXT_COL, SHADOW, DIM = WHITE, 0.55, 0.3
else:
    centre, edge = np.array([255, 255, 255]) / 255, np.array([226, 230, 228]) / 255
    TEXT_COL, SHADOW, DIM = BRAND_GREEN, 0.18, 0.75
k = np.clip(rad / 0.75, 0, 1)[..., None] ** 1.4
bg = centre * (1 - k) + edge * k
bg += np.random.default_rng(7).normal(0, 0.6 / 255, bg.shape)  # anti-banding dither
bg = bg.astype(np.float32)
del yy, xx, rad, k

# soft charcoal contact shadow under the emblem
pad = int(sq_w * 0.25)
sh_img = Image.new("L", (sq_w + 2 * pad, sq_h + 2 * pad), 0)
sh_img.paste(255, (pad, pad, pad + sq_w, pad + sq_h))
shadow = np.array(sh_img.filter(ImageFilter.GaussianBlur(sq_w * 0.06)), np.float32) / 255 * SHADOW
sh_off = int(sq_w * 0.03)


# ---------------------------------------------------------------- easing
def clamp01(v):
    return min(max(v, 0.0), 1.0)


def prog(t, a, b):
    return clamp01((t - a) / (b - a))


def ease_out(p):
    return 1 - (1 - p) ** 3


def ease_io(p):
    return 4 * p ** 3 if p < 0.5 else 1 - (-2 * p + 2) ** 3 / 2


def blend(region, alpha, colour):
    a = alpha[..., None]
    region *= 1 - a
    region += a * colour


def shift(a, dx, dy):
    out = np.zeros_like(a)
    h, w = a.shape
    if abs(dx) >= w or abs(dy) >= h:
        return out
    out[max(dy, 0):h + min(dy, 0), max(dx, 0):w + min(dx, 0)] = a[max(-dy, 0):h - max(dy, 0), max(-dx, 0):w - max(dx, 0)]
    return out


# ---------------------------------------------------------------- timeline
ARROW_T0, ARROW_LEN, ARROW_GAP = 1.0, 0.9, 0.33
TEXT_T = [(2.7, 3.5), (3.1, 3.9), (3.7, 4.4)]
SWEEP = (4.6, 5.6)  # final clean hold 5.6 -> 8.0 s

out = sys.stdout.buffer
for f in range(N):
    t = f / FPS
    frame = bg * (DIM + (1 - DIM) * ease_io(prog(t, 0.0, 1.0)))  # opening light-up
    # every logo layer lives inside the logo box; composite there
    cx0, cy0 = place(LOGO)
    cw, ch = round((lx1 - lx0) * S), round((ly1 - ly0) * S)
    m = pad + sh_off
    rx0, ry0 = min(cx0, ex - pad), min(cy0, ey - pad)
    rx1, ry1 = max(cx0 + cw, ex + sq_w + m), max(cy0 + ch, ey + sq_h + m) + texts[-1][1].shape[0]
    reg = frame[ry0:ry1, rx0:rx1]
    logo_a = np.zeros(reg.shape[:2], np.float32)

    # emblem square
    pe = ease_out(prog(t, 0.5, 1.3))
    lift = round((1 - pe) * sq_h * 0.04)
    if pe > 0:
        sy, sx = ey - pad + sh_off - ry0 + lift, ex - pad - rx0
        reg_s = reg[sy:sy + shadow.shape[0], sx:sx + shadow.shape[1]]
        reg_s *= 1 - shadow[..., None] * pe
        y_, x_ = ey - ry0 + lift, ex - rx0
        reg[y_:y_ + sq_h, x_:x_ + sq_w] = reg[y_:y_ + sq_h, x_:x_ + sq_w] * (1 - pe) + BRAND_GREEN * pe
        logo_a[y_:y_ + sq_h, x_:x_ + sq_w] = pe

        # arrows glide up-left into place, emerging from inside the square
        for i, a in enumerate(arrows):
            p = prog(t, ARROW_T0 + i * ARROW_GAP, ARROW_T0 + i * ARROW_GAP + ARROW_LEN)
            if p <= 0:
                continue
            d = round((1 - ease_out(p)) * sq_w * 0.75)
            al = shift(a, d, d) * clamp01(p / 0.35) * pe
            blend(reg[y_:y_ + sq_h, x_:x_ + sq_w], al, WHITE)

    # text lines: soft left-to-right wipe with a gentle rise
    for ((tx, ty), a), (a0, a1) in zip(texts, TEXT_T):
        p = prog(t, a0, a1)
        if p <= 0:
            continue
        pe_t = ease_out(p)
        h_, w_ = a.shape
        edge_w = 0.25
        wipe = np.clip((pe_t * (1 + edge_w) - np.linspace(0, 1, w_, dtype=np.float32)) / edge_w, 0, 1)
        al = a * wipe[None, :] * pe_t
        dy = round((1 - pe_t) * h_ * 0.35)
        y_, x_ = ty - ry0 + dy, tx - rx0
        blend(reg[y_:y_ + h_, x_:x_ + w_], al, TEXT_COL)
        logo_a[y_:y_ + h_, x_:x_ + w_] = np.maximum(logo_a[y_:y_ + h_, x_:x_ + w_], al)

    # single refined light sweep across the finished logo
    ps = prog(t, *SWEEP)
    if 0 < ps < 1:
        hh, ww = reg.shape[:2]
        gy, gx = np.mgrid[0:hh, 0:ww].astype(np.float32)
        u = (gx + gy * 0.45) / (ww + hh * 0.45)
        pos = -0.2 + 1.4 * ease_io(ps)
        band = np.exp(-(((u - pos) / 0.06) ** 2)) * np.sin(np.pi * ps)
        hl = (band * logo_a * 0.45)[..., None]
        reg += (1 - reg) * hl

    out.write((np.clip(frame, 0, 1) * 255 + 0.5).astype(np.uint8).tobytes())

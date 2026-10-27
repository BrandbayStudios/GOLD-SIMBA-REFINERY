"""Discovery Freight & Logistics - 15 s cinematic intro (stylised).

Scenes 1-3 are built from drawn vehicle silhouettes and parallax layers.
Scenes 4-5 assemble the logo from layers cut out of logo-source.png, so the
emblem, the four arrows and all lettering are the original artwork.

Usage:  python3 intro.py WIDTH [START_FRAME END_FRAME] > frames.rgb
Writes raw RGB24 frames to stdout; see make-intro.sh.
"""
import math
import sys
from collections import deque

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

HERE = __file__.rsplit("/", 1)[0] if "/" in __file__ else "."
W = int(sys.argv[1]) if len(sys.argv) > 1 else 1920
H = W * 9 // 16
U = W / 1920  # all layout below is in 1920x1080 design pixels
FPS, DUR = 30, 15.0
N = int(FPS * DUR)
F0 = int(sys.argv[2]) if len(sys.argv) > 2 else 0
F1 = int(sys.argv[3]) if len(sys.argv) > 3 else N


def hexc(h):
    return np.array([int(h[i:i + 2], 16) for i in (1, 3, 5)], np.float32) / 255


BRAND_GREEN = np.array([0, 92, 81], np.float32) / 255  # sampled from the logo
WHITE = np.ones(3, np.float32)


# ================================================================ helpers
def clamp01(v):
    return min(max(v, 0.0), 1.0)


def prog(t, a, b):
    return clamp01((t - a) / (b - a))


def ease_out(p):
    return 1 - (1 - p) ** 3


def ease_io(p):
    return 4 * p ** 3 if p < 0.5 else 1 - (-2 * p + 2) ** 3 / 2


def ease_back(p, s=1.3):
    q = p - 1
    return 1 + (s + 1) * q ** 3 + s * q ** 2


def lerp(a, b, p):
    return a + (b - a) * p


def vgrad(stops, h=H):
    """Vertical colour ramp, stops = [(0..1, '#hex'), ...] -> (h, 1, 3)."""
    ys = np.linspace(0, 1, h, dtype=np.float32)
    pos = [s[0] for s in stops]
    cols = np.array([hexc(s[1]) for s in stops])
    out = np.stack([np.interp(ys, pos, cols[:, c]) for c in range(3)], -1)
    return out[:, None, :].astype(np.float32)


def hblur(img, r):
    """Horizontal box blur (motion blur) of an (h, w, c) array."""
    r = int(r)
    if r < 1:
        return img
    c = np.cumsum(np.pad(img, ((0, 0), (r + 1, r), (0, 0)), mode="edge"), axis=1, dtype=np.float32)
    return (c[:, 2 * r + 1:] - c[:, :-2 * r - 1]) / (2 * r + 1)


def over(dst, src, x, y, opacity=1.0, tint=None):
    """Composite straight-alpha RGBA sprite `src` onto `dst` at design-free pixel (x, y)."""
    h, w = src.shape[:2]
    x, y = int(round(x)), int(round(y))
    x0, y0, x1, y1 = max(x, 0), max(y, 0), min(x + w, dst.shape[1]), min(y + h, dst.shape[0])
    if x0 >= x1 or y0 >= y1 or opacity <= 0:
        return
    s = src[y0 - y:y1 - y, x0 - x:x1 - x]
    a = s[..., 3:4] * opacity
    d = dst[y0:y1, x0:x1]
    d *= 1 - a
    d += a * (s[..., :3] if tint is None else tint)


def add_light(dst, mask, colour, x=0, y=0):
    """Screen-blend a light mask (h, w) into dst at (x, y)."""
    h, w = mask.shape
    x0, y0, x1, y1 = max(x, 0), max(y, 0), min(x + w, dst.shape[1]), min(y + h, dst.shape[0])
    if x0 >= x1 or y0 >= y1:
        return
    m = mask[y0 - y:y1 - y, x0 - x:x1 - x, None] * colour
    d = dst[y0:y1, x0:x1]
    d += (1 - d) * m


class Pen:
    def __init__(self, d, k):
        self.d, self.k = d, k

    def poly(self, pts, c):
        self.d.polygon([(x * self.k, y * self.k) for x, y in pts], fill=c)

    def rect(self, x0, y0, x1, y1, c):
        k = self.k
        self.d.rectangle([x0 * k, y0 * k, max(x1 * k - 1, x0 * k), max(y1 * k - 1, y0 * k)], fill=c)

    def circ(self, cx, cy, r, c):
        k = self.k
        self.d.ellipse([(cx - r) * k, (cy - r) * k, (cx + r) * k, (cy + r) * k], fill=c)

    def line(self, pts, c, w):
        self.d.line([(x * self.k, y * self.k) for x, y in pts], fill=c, width=max(1, round(w * self.k)), joint="curve")


def sprite(wu, hu, ppu, draw, ss=2, *args):
    """Draw a sprite in its own units, supersampled; ppu = design px per unit."""
    k = ppu * U
    im = Image.new("RGBA", (math.ceil(wu * k * ss), math.ceil(hu * k * ss)), (0, 0, 0, 0))
    draw(Pen(ImageDraw.Draw(im, "RGBA"), k * ss), *args)
    im = im.convert("RGBa").resize((math.ceil(wu * k), math.ceil(hu * k)), Image.LANCZOS).convert("RGBA")
    return np.asarray(im).astype(np.float32) / 255


def haze(spr, colour, amount):
    out = spr.copy()
    out[..., :3] = out[..., :3] * (1 - amount) + hexc(colour) * amount
    return out


# ================================================================ vehicles (side view)
CONT = ["#3c6a8c", "#9b4b3b", "#c27c3c", "#4e7a5c", "#8b9297", "#c8cdcd", "#3f7e7a", "#6f4f68", "#b39a48"]


def ribs(p, x0, y0, x1, y1, step=5.5):
    for rx in np.arange(x0 + 3, x1 - 1, step):
        p.rect(rx, y0 + 1, rx + 0.9, y1 - 1, "#00000026")
    p.rect(x0, y0, x1, y0 + 0.9, "#ffffff30")


def draw_ship(p):
    r = np.random.default_rng(3)
    for bay in range(17):
        x0 = 172 + bay * 45.6
        for row in range(int(r.integers(3, 7))):
            y1 = 146 - row * 19.2
            p.rect(x0, y1 - 18.4, x0 + 44, y1, CONT[r.integers(len(CONT))])
            ribs(p, x0, y1 - 18.4, x0 + 44, y1)
    p.poly([(0, 144), (968, 144), (1000, 126), (986, 176), (958, 232), (34, 232), (0, 204)], "#2a3134")
    p.poly([(12, 214), (965, 214), (958, 232), (34, 232)], "#6e2b25")
    p.rect(0, 144, 968, 149, "#4b5458")
    p.rect(44, 40, 146, 144, "#d4d9d6")
    for y in (60, 80, 100, 120):
        p.rect(44, y, 146, y + 2, "#9aa3a1")
    p.rect(38, 44, 152, 54, "#1c2427")
    p.rect(70, 8, 98, 40, "#30383b")
    p.rect(70, 8, 98, 14, "#1d2224")
    p.rect(978, 92, 982, 144, "#2a3134")


def draw_plane(p):  # cargo jet, nose to the right
    c = "#1a2124"
    p.poly([(0, 56), (110, 62), (560, 62), (580, 67), (595, 77), (600, 87), (595, 97), (580, 103),
            (560, 107), (130, 107), (0, 70)], c)
    p.poly([(4, 60), (80, 60), (40, 0), (16, 0)], c)
    p.poly([(20, 70), (95, 73), (60, 82), (8, 79)], c)
    p.poly([(215, 96), (345, 98), (305, 114), (200, 112)], c)
    p.circ(258, 116, 9, c)
    p.rect(232, 108, 282, 124, c)
    p.rect(300, 104, 344, 118, c)
    p.rect(536, 70, 560, 76, "#3a464a")  # cockpit glint


def draw_truck(p, cc):  # faces left
    p.rect(72, 6, 298, 62, cc)
    ribs(p, 72, 6, 298, 62, 7)
    p.rect(70, 62, 298, 70, "#1c1f20")
    p.poly([(2, 70), (2, 30), (8, 12), (62, 10), (66, 18), (66, 70)], "#d9dddb")
    p.poly([(6, 30), (12, 16), (36, 15), (36, 31)], "#25343a")
    p.rect(44, 18, 60, 40, "#25343a")
    p.rect(0, 62, 30, 72, "#1b1e1f")
    p.rect(2, 44, 6, 52, "#f3e3b0")
    for wx in (20, 52, 214, 240, 266):
        p.circ(wx, 74, 11, "#121415")
        p.circ(wx, 74, 5, "#6c7275")


def draw_train(p, ncars):  # faces left
    r = np.random.default_rng(11)
    p.poly([(0, 72), (0, 36), (16, 16), (64, 14), (64, 72)], "#2d3437")
    p.rect(64, 24, 258, 72, "#3a4245")
    p.rect(0, 58, 258, 62, "#c9a227")
    p.poly([(4, 36), (18, 20), (40, 20), (40, 36)], "#1a2427")
    p.rect(46, 22, 60, 36, "#1a2427")
    for gx in np.arange(90, 250, 12):
        p.rect(gx, 30, gx + 6, 48, "#2c3336")
    p.rect(0, 72, 258, 78, "#1c1f20")
    for bx in (48, 206):
        p.rect(bx - 30, 76, bx + 30, 84, "#141617")
        for wx in (bx - 17, bx, bx + 17):
            p.circ(wx, 87, 7, "#0f1112")
    x = 262
    for _ in range(ncars):
        p.rect(x, 66, x + 218, 74, "#202325")
        for bx in (x + 30, x + 188):
            p.rect(bx - 20, 74, bx + 20, 82, "#141617")
            for wx in (bx - 9, bx + 9):
                p.circ(wx, 87, 7, "#0f1112")
        if r.random() < 0.5:
            for cx0 in (x + 6, x + 110):
                p.rect(cx0, 20, cx0 + 102, 66, CONT[r.integers(len(CONT))])
                ribs(p, cx0, 20, cx0 + 102, 66)
        else:
            p.rect(x + 8, 20, x + 210, 66, CONT[r.integers(len(CONT))])
            ribs(p, x + 8, 20, x + 210, 66)
        x += 222


def draw_stacker(p, ang, cont):  # reach stacker, faces right
    px, py, L = 104, 150, 250
    a = math.radians(ang)
    tx, ty = px + L * math.cos(a), py - L * math.sin(a)
    nx, ny = math.sin(a) * 11, math.cos(a) * 11
    p.rect(tx - 100, ty + 14, tx + 100, ty + 66, cont)
    ribs(p, tx - 100, ty + 14, tx + 100, ty + 66, 6)
    p.rect(tx - 76, ty + 6, tx + 76, ty + 14, "#2a2b2b")
    p.rect(14, 132, 84, 214, "#b58a17")
    p.rect(30, 168, 300, 214, "#c79a1e")
    p.rect(30, 206, 300, 214, "#2a2b2b")
    p.rect(150, 104, 206, 168, "#2b3235")
    p.rect(156, 110, 200, 150, "#58737c")
    p.line([(172, 192), (px + 0.45 * L * math.cos(a), py - 0.45 * L * math.sin(a))], "#7b8082", 8)
    p.poly([(px - nx, py - ny), (tx - nx, ty - ny), (tx + nx, ty + ny), (px + nx, py + ny)], "#d4a524")
    p.circ(px, py, 13, "#8a6a12")
    for wx in (92, 262):
        p.circ(wx, 222, 27, "#121314")
        p.circ(wx, 222, 11, "#5b6062")


def draw_forklift(p):  # faces right, carrying a pallet
    p.rect(114, 58, 136, 98, "#b08655")
    p.rect(136, 58, 158, 98, "#a37a4b")
    p.rect(114, 36, 146, 58, "#b8905e")
    p.rect(114, 76, 158, 78, "#e6d9bf")
    p.rect(112, 98, 158, 104, "#7a5a36")
    p.rect(108, 104, 160, 108, "#2a2d2e")
    p.rect(102, 8, 110, 112, "#2a2d2e")
    p.rect(14, 58, 100, 104, "#d08a1f")
    p.rect(6, 70, 30, 104, "#3a3d3e")
    p.line([(30, 58), (36, 14), (96, 14), (100, 58)], "#1d2021", 4)
    p.rect(50, 40, 70, 58, "#1d2021")
    for wx in (34, 88):
        p.circ(wx, 112, 13, "#121314")
        p.circ(wx, 112, 5, "#5b6062")


def draw_crane(p, c):  # ship-to-shore gantry crane
    p.line([(120, 420), (120, 150)], c, 9)
    p.line([(230, 420), (230, 150)], c, 9)
    p.line([(120, 300), (230, 300)], c, 6)
    p.line([(120, 420), (230, 300)], c, 3)
    p.line([(230, 420), (120, 300)], c, 3)
    p.rect(0, 140, 400, 153, c)
    p.line([(150, 140), (175, 28), (200, 140)], c, 7)
    p.line([(175, 30), (8, 142)], c, 3)
    p.line([(175, 30), (392, 142)], c, 3)
    p.rect(128, 116, 242, 140, c)
    p.rect(58, 153, 92, 163, c)
    p.line([(75, 163), (75, 250)], c, 1.5)
    p.rect(52, 250, 98, 257, c)


def draw_yard(p):
    r = np.random.default_rng(5)
    x = 0
    while x < 2500:
        for b in range(int(r.integers(3, 7))):
            for row in range(int(r.integers(2, 5))):
                y1 = 80 - row * 19.2
                p.rect(x, y1 - 18.4, x + 44, y1, CONT[r.integers(len(CONT))])
                ribs(p, x, y1 - 18.4, x + 44, y1)
            x += 45.6
        x += 60


def draw_warehouse(p):
    p.rect(0, 40, 720, 300, "#5e6564")
    p.poly([(-10, 42), (360, 4), (730, 42)], "#4a5150")
    for gx in np.arange(8, 720, 16):
        p.rect(gx, 44, gx + 1.5, 300, "#00000018")
    p.rect(410, 132, 570, 300, "#2a2e2e")
    p.rect(420, 142, 560, 300, "#e2bd82")
    p.rect(420, 142, 560, 170, "#c79d62")
    for lx in (120, 250, 650):
        p.rect(lx, 110, lx + 26, 118, "#f4e2b8")


# ================================================================ vehicles (top view, for the map)
def draw_ship_top(p):
    r = np.random.default_rng(8)
    p.poly([(0, 6), (180, 6), (220, 22), (180, 38), (0, 38)], "#3a4448")
    for bay in range(9):
        for row in range(3):
            p.rect(34 + bay * 17, 9 + row * 9, 50 + bay * 17, 17 + row * 9, CONT[r.integers(len(CONT))])
    p.rect(8, 8, 26, 36, "#e9ecea")


def draw_plane_top(p):
    c = "#e8ecea"
    p.poly([(6, 60), (120, 58), (138, 65), (120, 72), (6, 70)], c)
    p.poly([(62, 60), (40, 2), (54, 2), (92, 60)], c)
    p.poly([(62, 70), (40, 128), (54, 128), (92, 70)], c)
    p.poly([(6, 62), (0, 42), (9, 42), (24, 62)], c)
    p.poly([(6, 68), (0, 88), (9, 88), (24, 68)], c)
    for ey in (34, 96):
        p.rect(60, ey - 3, 74, ey + 3, c)


def draw_truck_top(p, cc):
    p.rect(0, 1, 10, 13, "#e3e6e4")
    p.rect(11, 0, 60, 14, cc)


def draw_train_top(p):
    r = np.random.default_rng(4)
    p.rect(0, 0, 30, 9, "#cfd3d1")
    for i in range(9):
        p.rect(32 + i * 28, 0, 58 + i * 28, 9, CONT[r.integers(len(CONT))])


# ================================================================ logo cut from the source artwork
src = np.array(Image.open(f"{HERE}/logo-source.png").convert("RGB")).astype(np.float32) / 255
ink = 1 - src[..., 0]  # green ink has R=0, paper is white
SQ = (67, 39, 165, 139)
LOGO = (52, 39, 179, 183)
TEXT_ROWS = [(141, 160), (161, 173), (174, 186)]  # DISCOVERY / FREIGHT / tagline

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
for _ in range(2):
    grow = lab.copy()
    for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1), (1, 1), (-1, -1), (1, -1), (-1, 1)):
        sh = np.roll(np.roll(lab, dy, 0), dx, 1)
        grow = np.where((grow == 0) & (sh > 0) & (white > 0.02), sh, grow)
    lab = grow

lx0, ly0, lx1, ly1 = LOGO
S = 0.62 * H / (ly1 - ly0)
OX = W / 2 - (lx0 + lx1) / 2 * S
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


ex, ey = place(SQ)
sq_w, sq_h = round((x1 - x0) * S), round((y1 - y0) * S)
arrows = [up(white * (lab == i), SQ)[:sq_h, :sq_w] for i in range(1, 5)]
arrows.sort(key=lambda a: np.where(a > 0.5)[1].min())
# each arrow's shaft: tail (where it meets the square edge) -> tip
shafts = []
for a in arrows:
    ys, xs = np.where(a > 0.5)
    d = xs + ys
    tip = (xs[d.argmin()] + ex, ys[d.argmin()] + ey)
    tail_sel = d >= d.max() - 2
    tail = (xs[tail_sel].mean() + ex, ys[tail_sel].mean() + ey)
    shafts.append((tail, tip))
texts = []
for r0, r1 in TEXT_ROWS:
    box = (lx0, r0, lx1, r1)
    texts.append((place(box), up(ink[r0:r1, lx0:lx1], box)))

pad = int(sq_w * 0.25)
sh_img = Image.new("L", (sq_w + 2 * pad, sq_h + 2 * pad), 0)
sh_img.paste(255, (pad, pad, pad + sq_w, pad + sq_h))
shadow = np.array(sh_img.filter(ImageFilter.GaussianBlur(sq_w * 0.06)), np.float32) / 255 * 0.55
sh_off = int(sq_w * 0.03)

# ================================================================ shared stage pieces
yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
rad = np.sqrt(((xx - W / 2) / W) ** 2 + ((yy - H * 0.45) / H) ** 2 * 0.8)
k = np.clip(rad / 0.75, 0, 1)[..., None] ** 1.4
STUDIO = (hexc("#0c4238") * (1 - k) + hexc("#021411") * k).astype(np.float32)
VIGNETTE = (1 - 0.38 * np.clip((np.sqrt(((xx - W / 2) / W) ** 2 + ((yy - H / 2) / H) ** 2) - 0.25) / 0.45, 0, 1) ** 1.6)[..., None]
grain_rng = np.random.default_rng(1)
GRAIN = [grain_rng.normal(0, 0.012, (H, W)).astype(np.float32)[..., None] for _ in range(6)]
del rad, k

# ---------------------------------------------------------------- scene 1: sea at sunrise
HOR = int(0.60 * H)
SKY1 = vgrad([(0, "#0b2c30"), (0.30, "#21474b"), (0.48, "#7d735e"), (0.565, "#e2a868"), (0.60, "#f6d39a"), (1, "#f6d39a")])
SEA1 = vgrad([(0, "#3d6f86"), (0.12, "#24546f"), (1, "#061f2c")], H - HOR)
SUN = (0.72 * W, 0.588 * H)
d_sun = np.sqrt((xx - SUN[0]) ** 2 + (yy - SUN[1]) ** 2) / H
BASE1 = np.broadcast_to(SKY1, (H, W, 3)).copy()
BASE1 += (np.exp(-(d_sun / 0.09) ** 2) * 0.55 + np.exp(-(d_sun / 0.4) ** 2) * 0.18)[..., None] * hexc("#ffd9a0")
BASE1[d_sun < 0.024] = hexc("#fff3d6")
BASE1 = np.clip(BASE1, 0, 1)
BASE1[HOR:] = SEA1
tex_rng = np.random.default_rng(2)
RIP_W = 3 * W
RIP = np.array(Image.fromarray(tex_rng.random((300, 420)).astype(np.float32), "F").resize((RIP_W, 512), Image.BICUBIC))
sea_y = np.arange(H - HOR, dtype=np.float32)
RIP_ROWS = (511 * (1 - ((sea_y + 1) / (H - HOR)) ** 0.45)).astype(int)
RIP_DEPTH = 0.15 + 0.85 * (sea_y / (H - HOR))
SUN_COL = np.exp(-(((np.arange(W) - SUN[0]) / ((0.02 + 0.3 * sea_y[:, None] / (H - HOR)) * W)) ** 2)).astype(np.float32)
del d_sun

SHIP_PPU = 2.2
SHIP = sprite(1000, 232, SHIP_PPU, draw_ship)
SHIP[..., :3] *= np.array([0.86, 0.80, 0.74], np.float32)
PLANE = sprite(600, 125, 1.15, draw_plane)
FL = 1000 * SHIP_PPU  # foam runs the length of the hull, heaviest at the bow
fo = Image.new("L", (int((FL + 80) * U), int(80 * U)), 0)
fd = ImageDraw.Draw(fo)
fd.ellipse([40 * U, 34 * U, (FL + 40) * U, 46 * U], fill=70)
fd.ellipse([(FL - 260) * U, 22 * U, (FL + 70) * U, 58 * U], fill=200)
FOAM = np.array(fo.filter(ImageFilter.GaussianBlur(9 * U)), np.float32) / 255
ct = np.zeros((int(24 * U), int(900 * U)), np.float32)
ct[int(8 * U):int(16 * U)] = np.linspace(0, 1, ct.shape[1], dtype=np.float32) ** 2
CONTRAIL = np.array(Image.fromarray((ct * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(3 * U)), np.float32) / 255 * 0.35


def scene1(t):
    f = BASE1.copy()
    off = t * 260 * U
    cols = (np.arange(W)[None, :] + (off * RIP_DEPTH)[:, None]).astype(int) % RIP_W
    rip = RIP[RIP_ROWS[:, None], cols]
    light = np.clip((rip - 0.6) * 6, 0, 1)
    sea = f[HOR:]
    sea *= (0.93 + 0.14 * rip)[..., None]
    sea += (light * (0.025 + 0.85 * SUN_COL))[..., None] * hexc("#ffd59a")
    # container ship: the camera overtakes it, so it slides left while sailing right
    sx = lerp(-100, -980, ease_io(prog(t, 0, 3.4)) * 0.5 + prog(t, 0, 3.4) * 0.5) * U
    wl = 0.80 * H + math.sin(t * 1.4) * 3 * U
    over(f, SHIP, sx, wl - SHIP.shape[0] + 2 * U)
    add_light(f, FOAM * 0.8, hexc("#f2f5f2"), int(sx - 40 * U), int(wl - 40 * U))
    # bow spray
    bow = (sx + 990 * SHIP_PPU * U, wl - 6 * U)
    sp = Image.new("L", (int(420 * U), int(260 * U)), 0)
    sd = ImageDraw.Draw(sp)
    pr = np.random.default_rng(9)
    for _ in range(70):
        ph, vx, vy, rr = pr.random(), pr.uniform(40, 220), pr.uniform(-260, -120), pr.uniform(1.5, 3.5)
        tau = (t * 1.25 + ph) % 1.0 * 0.8
        px = 200 + vx * tau
        py = 240 + vy * tau + 420 * tau * tau
        if py < 250:
            a = int(220 * (1 - tau / 0.8))
            sd.ellipse([(px - rr) * U, (py - rr) * U, (px + rr) * U, (py + rr) * U], fill=a)
    spray = np.array(sp.filter(ImageFilter.GaussianBlur(1.6 * U)), np.float32) / 255
    add_light(f, spray, hexc("#f4f6f4"), int(bow[0] - 200 * U), int(bow[1] - 240 * U))
    return f


def plane_layer(f, t):
    p = prog(t, 1.35, 3.35)
    if p <= 0 or p >= 1:
        return
    nose = lerp(-0.12, 1.65, p) * W
    px = nose - PLANE.shape[1]
    py = (0.20 - 0.07 * p) * H
    add_light(f, CONTRAIL, WHITE, int(px - CONTRAIL.shape[1] + 30 * U), int(py + 96 * 1.15 * U))
    over(f, PLANE, px, py)


# ---------------------------------------------------------------- scene 2a: container terminal
SKY2 = np.broadcast_to(vgrad([(0, "#1f4146"), (0.4, "#5d6f6a"), (0.66, "#d8b98c"), (1, "#d8b98c")]), (H, W, 3)).copy()
GROUND2 = vgrad([(0, "#4a4d4b"), (1, "#1b1e1f")], H - int(0.68 * H))
SKY2[int(0.68 * H):] = GROUND2
CRANE = sprite(400, 425, 1.0, draw_crane, 2, "#4f5f61")
BERTH = haze(sprite(1000, 232, 0.62, draw_ship), "#a9a08a", 0.45)
YARD = haze(sprite(2500, 82, 1.25, draw_yard), "#b9ad94", 0.28)
HOUSE = haze(sprite(720, 300, 0.95, draw_warehouse), "#b9ad94", 0.2)
FORK = sprite(162, 128, 0.95, draw_forklift)
TRUCKS = [sprite(300, 86, 1.7, draw_truck, 2, c) for c in ("#3c6a8c", "#9b4b3b", "#c8cdcd")]
STACK2 = sprite(220, 110, 1.5, lambda p: [p.rect(10, 6 + r * 52, 210, 56 + r * 52, c) or ribs(p, 10, 6 + r * 52, 210, 56 + r * 52, 6)
                                           for r, c in enumerate(("#4e7a5c", "#3c6a8c"))])


def scene2a(t, extra=0.0):
    f = SKY2.copy()
    cam = (160 * (t - 2.6) + extra) * U
    base_far = 0.665 * H
    over(f, BERTH, -200 * U - cam * 0.25, base_far - BERTH.shape[0] + 10 * U)
    for i in range(6):
        over(f, CRANE, (i * 380 - 150) * U - cam * 0.25, base_far - CRANE.shape[0] + 30 * U)
    over(f, YARD, -300 * U - cam * 0.5, 0.71 * H - YARD.shape[0])
    hx = 1180 * U - cam * 0.5
    over(f, HOUSE, hx, 0.715 * H - HOUSE.shape[0])
    fp = prog(t, 3.0, 4.9)
    if fp > 0:
        over(f, FORK, hx + (400 + 260 * fp) * 0.95 * U, 0.715 * H - FORK.shape[0])
    # lane markings
    yl = int(0.90 * H)
    dash = 160 * U
    o = (cam * 1.0) % dash
    xs = np.arange(-dash, W + dash, dash) - o
    for x in xs:
        f[yl:yl + int(5 * U), max(int(x), 0):max(int(x + 80 * U), 0)] = hexc("#c9c6b8")
    # reach stacker lifting a container onto a stack
    lift = ease_io(prog(t, 3.0, 4.7))
    st = sprite(470, 252, 1.5, draw_stacker, 2, lerp(6, 30, lift), "#9b4b3b")
    sx = 520 * U - cam * 0.75
    over(f, STACK2, sx + 560 * 1.5 * U - 300 * U, 0.83 * H - STACK2.shape[0])
    over(f, st, sx, 0.83 * H - st.shape[0])
    for i, tr in enumerate(TRUCKS):
        tx = (1500 + i * 620 - 380 * (t - 2.6)) * U - cam
        over(f, tr, tx, 0.975 * H - tr.shape[0])
    return f


# ---------------------------------------------------------------- scene 2b: train in the countryside
SKY3 = vgrad([(0, "#284e53"), (0.35, "#5f7b74"), (0.62, "#e8cfa0"), (1, "#e8cfa0")], 2 * H)
glow3 = np.exp(-((((xx - 0.25 * W) / W) ** 2 + ((yy - 0.55 * H) / H) ** 2) / 0.05))[..., None] * hexc("#ffe0a8") * 0.35
TRAIN_PPU = 2.0
TRAIN = sprite(262 + 222 * 10, 96, TRAIN_PPU, draw_train, 2, 10)
pole = np.zeros((int(0.95 * H), int(70 * U), 4), np.float32)
pole[:, int(30 * U):int(40 * U)] = [*hexc("#121a15"), 1]
pole[int(40 * U):int(46 * U), int(14 * U):int(56 * U)] = [*hexc("#121a15"), 1]
POLE = hblur(pole, 8 * U)
HILLS = [  # base, (amp, period, phase)..., colour, parallax px/s
    (0.565, ((25, 310, 1), (15, 97, 0)), "#6c8a80", 40),
    (0.635, ((40, 420, 2), (18, 130, 1)), "#45664f", 140),
    (0.725, ((18, 260, 0), (8, 71, 2), (4, 23, 1)), "#2c4a35", 420),
]


def scene2b(t, extra=0.0, rise=0.0):
    tt = t - 4.6
    sky_off = int(rise * 0.15 * H)
    f = np.broadcast_to(SKY3[H - sky_off:2 * H - sky_off], (H, W, 3)).copy()
    f += glow3
    xs = np.arange(W, dtype=np.float32) / U
    for (base, waves, col, spd), lift in zip(HILLS, (0.35, 0.55, 0.9)):
        o = spd * tt + extra * spd / 420
        yc = base * H / U + sum(a * np.sin((xs + o) / p + ph) for a, p, ph in waves)
        yc = yc * U + rise * lift * H
        a = np.clip(yy[:, :1] - yc[None, :] + 0.5, 0, 1)[..., None]
        f *= 1 - a
        f += a * hexc(col)
    gy = rise * 0.9 * H
    emb0, rail = int(0.80 * H + gy), int(0.795 * H + gy)
    if emb0 < H:
        f[emb0:] = vgrad([(0, "#2b2c27"), (0.25, "#22251f"), (1, "#16231b")], H)[: H - emb0]
    if rail < H:
        f[rail:rail + max(1, int(3 * U))] = hexc("#9a9a92")
    # camera races alongside the train, slowly falling behind it
    tx = (380 - 150 * tt + extra * 0.15) * U
    over(f, TRAIN, tx, 0.80 * H + gy - TRAIN.shape[0] + 4 * U)
    for i in range(-1, 4):
        px = ((i * 900 + 1500 * tt + extra * 3) % 3600 - 600) * U
        over(f, POLE, W - px, 0.06 * H + rise * 1.2 * H)
    return f


# ---------------------------------------------------------------- scene 3: the network map
mr = np.random.default_rng(21)
MW, MH = int(W * 1.25), int(H * 1.25)
noise = np.array(Image.fromarray(mr.random((10, 18)).astype(np.float32), "F").resize((MW, MH), Image.BICUBIC))
noise += 0.15 * np.array(Image.fromarray(mr.random((40, 70)).astype(np.float32), "F").resize((MW, MH), Image.BICUBIC))
LAND = np.clip((noise - 0.62) / 0.03, 0, 1)
COAST = np.exp(-(((noise - 0.635) / (0.0035 * 1920 / W)) ** 2))  # thin line at the shore
MAPTEX = (LAND * 0.05 + COAST * 0.09)
gstep = int(96 * U)
MAPTEX[::gstep, :] += 0.035
MAPTEX[:, ::gstep] += 0.035
MAPTEX = MAPTEX.astype(np.float32)
SHIP_T = sprite(220, 44, 0.9, draw_ship_top)
PLANE_T = sprite(140, 130, 1.0, draw_plane_top)
TRUCK_T = sprite(60, 14, 1.4, draw_truck_top, 2, "#9b4b3b")
TRAIN_T = sprite(290, 9, 1.4, draw_train_top)


def map_vehicles(t):
    """Screen positions (design px) of each vehicle's route anchor at time t."""
    tt = t - 7.0
    return {
        "plane": (300 + 120 * tt, 230 - 10 * tt),
        "ship": (240 + 22 * tt, 870),
        "truck": (1640 - 60 * tt, 210),
        "train": (1560 - 70 * tt, 905),
    }


VEH_SPR = {"plane": (PLANE_T, 0.55), "ship": (SHIP_T, 0.5), "truck": (TRUCK_T, 0.5), "train": (TRAIN_T, 0.2)}
# match each vehicle to one arrow so the routes do not cross
_order = sorted(range(4), key=lambda i: shafts[i][1][1])  # arrows by tip height
ROUTE = {"plane": _order[0], "truck": _order[1], "ship": _order[3], "train": _order[2]}


def bez(p0, c, p1, s0, s1, n=80):
    ts = np.linspace(s0, s1, n)
    return [((1 - u) ** 2 * p0[0] + 2 * (1 - u) * u * c[0] + u * u * p1[0],
             (1 - u) ** 2 * p0[1] + 2 * (1 - u) * u * c[1] + u * u * p1[1]) for u in ts]


def routes(f, t, opacity):
    if opacity <= 0:
        return
    core = Image.new("L", (W, H), 0)
    cd = ImageDraw.Draw(core)
    q = 4
    glow = Image.new("L", (W // q, H // q), 0)
    gd = ImageDraw.Draw(glow)
    morph = ease_io(prog(t, 8.4, 9.2))
    for i, (name, pos) in enumerate(map_vehicles(t).items()):
        reveal = ease_out(prog(t, 7.35 + i * 0.12, 8.3 + i * 0.12))
        if reveal <= 0:
            continue
        tail, tip = shafts[ROUTE[name]]
        v = (pos[0] * U, pos[1] * U)
        mid = ((v[0] + tip[0]) / 2, (v[1] + tip[1]) / 2)
        nx, ny = -(tip[1] - v[1]) * 0.25, (tip[0] - v[0]) * 0.25
        c = (mid[0] + nx, mid[1] + ny)
        smid = ((tail[0] + tip[0]) / 2, (tail[1] + tip[1]) / 2)
        p0 = (lerp(v[0], tail[0], morph), lerp(v[1], tail[1], morph))
        cc = (lerp(c[0], smid[0], morph), lerp(c[1], smid[1], morph))
        pts = bez(p0, cc, tip, 0, reveal)
        cd.line(pts, fill=235, width=max(1, int(3 * U)), joint="curve")
        gd.line([(x / q, y / q) for x, y in pts], fill=200, width=max(1, int(10 * U / q)), joint="curve")
        hx, hy = pts[-1]
        r = 6 * U
        if reveal < 1:
            cd.ellipse([hx - r, hy - r, hx + r, hy + r], fill=255)
            gd.ellipse([(hx - 4 * r) / q, (hy - 4 * r) / q, (hx + 4 * r) / q, (hy + 4 * r) / q], fill=255)
    glow = glow.filter(ImageFilter.GaussianBlur(5 * U)).resize((W, H), Image.BILINEAR)
    m = (np.asarray(core, np.float32) / 255 + np.asarray(glow, np.float32) / 255 * 0.6) * opacity
    f += (1 - f) * np.clip(m, 0, 1)[..., None]


def scene3(t):
    f = STUDIO.copy()
    mo = 1 - ease_io(prog(t, 8.7, 9.5))
    if mo > 0:
        ox, oy = int((40 + (t - 7) * 18) * U), int((30 + (t - 7) * 10) * U)
        f += (MAPTEX[oy:oy + H, ox:ox + W] * mo)[..., None]
        vo = 1 - ease_io(prog(t, 8.5, 9.1))
        for name, (x, y) in map_vehicles(t).items():
            spr, ax = VEH_SPR[name]
            over(f, spr, x * U - spr.shape[1] * ax, y * U - spr.shape[0] / 2, vo)
    return f


# ---------------------------------------------------------------- scenes 4-5: the logo
SIL = [  # side silhouettes that briefly ghost behind the final logo
    (sprite(1000, 232, 0.42, draw_ship), 90, 1010),
    (sprite(300, 86, 0.75, draw_truck, 2, "#ffffff"), 760, 1015),
    (sprite(262 + 222 * 4, 96, 0.55, draw_train, 2, 4), 1180, 1012),
    (sprite(600, 125, 0.5, draw_plane), 1460, 230),
]


def arrow_layer(a, s, tip, dx, dy):
    """Scale arrow alpha `a` by s about its tip, then shift by (dx, dy)."""
    if s == 1 and dx == 0 and dy == 0:
        return a
    cx, cy = tip
    im = Image.fromarray(a, "F").transform(
        a.shape[::-1], Image.AFFINE,
        (1 / s, 0, cx - (cx + dx) / s, 0, 1 / s, cy - (cy + dy) / s), Image.BILINEAR)
    return np.asarray(im, np.float32)


def logo(f, t):
    logo_a = np.zeros((H, W), np.float32)
    pe = prog(t, 9.1, 9.75)
    if pe > 0:
        s = max(ease_back(pe, 1.2), 0.001)
        w2, h2 = sq_w * s, sq_h * s
        cx, cy = ex + sq_w / 2, ey + sq_h / 2
        a0, b0 = int(round(cx - w2 / 2)), int(round(cy - h2 / 2))
        a1, b1 = int(round(cx + w2 / 2)), int(round(cy + h2 / 2))
        sy, sx = ey - pad + sh_off, ex - pad
        reg_s = f[sy:sy + shadow.shape[0], sx:sx + shadow.shape[1]]
        reg_s *= 1 - shadow[..., None] * min(pe * 1.5, 1)
        f[b0:b1, a0:a1] = BRAND_GREEN
        logo_a[b0:b1, a0:a1] = 1
    for i, a in enumerate(arrows):
        p = prog(t, 9.35 + i * 0.2, 9.35 + i * 0.2 + 0.9)
        if p <= 0:
            continue
        e = ease_out(p)
        tip = (shafts[i][1][0] - ex, shafts[i][1][1] - ey)
        d = (1 - e) * sq_w * 0.8
        al = arrow_layer(a, 1 + 0.4 * (1 - e) ** 2, tip, d, d) * clamp01(p / 0.3)
        reg = f[ey:ey + sq_h, ex:ex + sq_w]
        reg *= 1 - al[..., None]
        reg += al[..., None] * WHITE
    for ((tx, ty), a), (a0, a1) in zip(texts, ((10.55, 11.3), (10.85, 11.6), (11.25, 11.9))):
        p = prog(t, a0, a1)
        if p <= 0:
            continue
        pt = ease_out(p)
        h_, w_ = a.shape
        wipe = np.clip((pt * 1.25 - np.linspace(0, 1, w_, dtype=np.float32)) / 0.25, 0, 1)
        al = a * wipe[None, :] * pt
        dy = round((1 - pt) * h_ * 0.35)
        reg = f[ty + dy:ty + dy + h_, tx:tx + w_]
        reg *= 1 - al[..., None]
        reg += al[..., None] * WHITE
        np.maximum(logo_a[ty + dy:ty + dy + h_, tx:tx + w_], al, out=logo_a[ty + dy:ty + dy + h_, tx:tx + w_])
    ps = prog(t, 12.0, 12.9)
    if 0 < ps < 1:
        u = (xx + yy * 0.45) / (W + H * 0.45)
        band = np.exp(-(((u - lerp(0.2, 0.8, ease_io(ps))) / 0.035) ** 2)) * math.sin(math.pi * ps)
        f += (1 - f) * (band * logo_a * 0.5)[..., None]


def scene45(t):
    f = STUDIO.copy()
    so = 0.10 * ease_io(prog(t, 11.5, 12.1)) * (1 - ease_io(prog(t, 12.4, 13.0)))
    if so > 0:
        for spr, x, y in SIL:
            over(f, spr, (x - 30 * (t - 11.5)) * U, y * U - spr.shape[0], so, WHITE)
    return f


# ================================================================ timeline
def render(t):
    if t < 3.3:
        f = scene1(t)
        if t > 2.5:
            b = lerp(-0.15, 1.15, ease_io(prog(t, 2.55, 3.3))) * W
            m = np.clip((b - np.arange(W, dtype=np.float32)) / (0.05 * W) + 0.5, 0, 1)[None, :, None]
            f = scene2a(t) * m + f * (1 - m)
        plane_layer(f, t)
    elif t < 4.62:
        f = scene2a(t)
    elif t < 5.0:  # whip pan from the terminal to the railway
        p = prog(t, 4.62, 5.0)
        e = ease_io(p)
        blur = math.sin(math.pi * p) * 0.08 * W
        if e < 0.5:
            f = scene2a(t, e * 2400)
        else:
            f = scene2b(t, -(1 - e) * 2400)
        f = hblur(f, blur)
    elif t < 7.0:
        f = scene2b(t, rise=ease_io(prog(t, 6.6, 7.6)))
    elif t < 7.7:
        m = ease_io(prog(t, 7.0, 7.7))
        f = scene2b(t, rise=ease_io(prog(t, 6.6, 7.6))) * (1 - m) + scene3(t) * m
    elif t < 9.5:
        f = scene3(t)
    else:
        f = scene45(t)
    if t >= 9.0:
        logo(f, t)
    if 7.3 < t < 10.3:
        routes(f, t, 1 - ease_io(prog(t, 9.7, 10.3)))
    return f


out = sys.stdout.buffer
for fi in range(F0, F1):
    t = fi / FPS
    f = render(t)
    f *= VIGNETTE
    f *= ease_io(prog(t, 0, 0.7))
    f += GRAIN[fi % len(GRAIN)]
    out.write((np.clip(f, 0, 1) * 255 + 0.5).astype(np.uint8).tobytes())

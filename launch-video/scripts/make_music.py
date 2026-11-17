"""Original 30 s launch-video music bed (120 BPM), synthesised from scratch with numpy. No samples, no licensing.

Structure (seconds): 0-4 tension build · 4 drop (logo) · 4-27 four-on-the-floor groove, arps from 7 · 27 final hit · fade out by 30.
Whooshes land on every scene cut (4, 7, 13, 19, 24, 27).
Usage: python3 scripts/make_music.py public/music.wav
"""
import sys
import wave

import numpy as np

SR = 44100
DUR = 30.0
BPM = 120
BEAT = 60 / BPM
N = int(SR * DUR)
rng = np.random.default_rng(7)
L = np.zeros(N)
R = np.zeros(N)


def add(buf_l, buf_r, start, sig, pan=0.0):
    i = int(start * SR)
    if i >= N:
        return
    sig = sig[: N - i]
    buf_l[i : i + len(sig)] += sig * (1 - max(pan, 0))
    buf_r[i : i + len(sig)] += sig * (1 + min(pan, 0))


def t(d):
    return np.arange(int(SR * d)) / SR


def hz(n):  # MIDI note -> Hz
    return 440.0 * 2 ** ((n - 69) / 12)


def env_ad(d, a=0.005, decay=8.0):
    x = t(d)
    return np.minimum(x / a, 1.0) * np.exp(-decay * x)


def kick():
    x = t(0.38)
    f = 45 + 110 * np.exp(-x * 28)
    ph = 2 * np.pi * np.cumsum(f) / SR
    return np.sin(ph) * np.exp(-x * 9) * 0.95


def clap():
    x = t(0.22)
    n = rng.standard_normal(len(x))
    n = n - np.convolve(n, np.ones(6) / 6, mode="same")  # crude high-pass
    return n * (np.exp(-x * 26) + 0.6 * np.exp(-((x - 0.012) ** 2) * 40000)) * 0.32


def hat(d=0.06, vol=0.12):
    x = t(d)
    n = rng.standard_normal(len(x))
    n = n - np.convolve(n, np.ones(3) / 3, mode="same")
    return n * np.exp(-x * 70) * vol


def pluck(freq, d=0.22):
    x = t(d)
    s = np.sin(2 * np.pi * freq * x) + 0.35 * np.sin(2 * np.pi * freq * 2 * x) + 0.12 * np.sin(2 * np.pi * freq * 3 * x)
    return s * env_ad(d, 0.003, 14) * 0.5


def pad(freqs, d):
    x = t(d)
    s = np.zeros_like(x)
    for f in freqs:
        for det in (-0.003, 0.0, 0.003):
            ff = f * (1 + det)
            s += np.sin(2 * np.pi * ff * x) + 0.3 * np.sin(2 * np.pi * ff * 2 * x)
    a = np.minimum(x / 0.5, 1.0)
    r = np.minimum((d - x) / 0.35, 1.0)
    return s * a * np.clip(r, 0, 1) / (len(freqs) * 3) * 0.55


def bass(freq, d):
    x = t(d)
    s = np.sin(2 * np.pi * freq * x) + 0.25 * np.sin(2 * np.pi * freq * 2 * x)
    return s * np.minimum(x / 0.01, 1) * np.exp(-x * 3.2) * 0.5


def whoosh(d=0.9, rise=True):
    x = t(d)
    n = rng.standard_normal(len(x))
    out = np.zeros_like(n)
    y = 0.0
    for i in range(len(n)):  # one-pole low-pass with a sweeping cutoff
        p = i / len(n)
        k = 0.02 + 0.55 * (p if rise else 1 - p) ** 2
        y += k * (n[i] - y)
        out[i] = y
    e = (x / d) ** 1.6 if rise else (1 - x / d) ** 1.6
    return out * e * 1.6


# --- chords: Am F C G, 2 bars each (4 s) ---
CHORDS = [(57, [57, 60, 64]), (53, [53, 57, 60]), (48, [48, 52, 55]), (55, [55, 59, 62])]
for ci in range(8):  # 8 chords * 4 s = 32 s
    start = ci * 4 * BEAT * 2 / 2 * 2  # 4 s per chord
    root, tones = CHORDS[ci % 4]
    start = ci * 4.0
    if start >= DUR:
        break
    p = pad([hz(n + 12) for n in tones] + [hz(tones[0])], 4.2)
    add(L, R, start, p * (0.65 if start < 4 else 1.0))

# --- 0-4 s tension: heartbeat + riser ---
for b in (0, 2, 4, 5, 6, 7):
    add(L, R, b * BEAT, kick() * 0.35)
x = t(4.0)
riser = rng.standard_normal(len(x)) * (x / 4.0) ** 3 * 0.25
riser = riser - np.convolve(riser, np.ones(20) / 20, mode="same")
add(L, R, 0, riser)
for s in range(8):  # rising ticks
    add(L, R, 2 + s * 0.25, hat(0.04, 0.05 + 0.02 * s), pan=0.2)

# --- groove 4-27 s ---
beat_i = 0
tt = 4.0
while tt < 27.0 - 1e-6:
    add(L, R, tt, kick())
    if beat_i % 2 == 1:
        add(L, R, tt, clap(), pan=-0.1)
    add(L, R, tt + BEAT / 2, hat(0.07, 0.13), pan=0.25)
    if beat_i % 4 == 3:
        add(L, R, tt + BEAT / 2, hat(0.2, 0.09), pan=-0.25)
    ci = int(tt // 4) % 4
    root = CHORDS[ci][0]
    add(L, R, tt + BEAT / 2, bass(hz(root - 12), BEAT * 0.9) * 0.9)  # offbeat bass (sidechain feel)
    beat_i += 1
    tt += BEAT

# --- arp from 7 s (16th notes) ---
tt = 7.0
step = 0
while tt < 27.0 - 1e-6:
    ci = int(tt // 4) % 4
    tones = CHORDS[ci][1]
    note = tones[[0, 1, 2, 1][step % 4]] + (24 if step % 8 >= 4 else 12)
    add(L, R, tt, pluck(hz(note)) * 0.85, pan=0.35 if step % 2 else -0.35)
    step += 1
    tt += BEAT / 4

# --- final hit + resolve ---
add(L, R, 27.0, kick() * 1.1)
add(L, R, 27.0, pad([hz(57), hz(60), hz(64), hz(69)], 3.0) * 1.4)
add(L, R, 27.0, bass(hz(45), 1.5) * 1.2)
x = t(2.6)
add(L, R, 27.0, np.sin(2 * np.pi * hz(81) * x) * np.exp(-x * 2.4) * 0.12)

# --- whooshes on cuts ---
for cut in (4.0, 7.0, 13.0, 19.0, 24.0, 27.0):
    add(L, R, cut - 0.55, whoosh(0.55, True) * 0.55, pan=-0.2)
    add(L, R, cut, whoosh(0.7, False) * 0.3, pan=0.2)

# --- master: gentle limiter, fade in/out ---
out = np.stack([L, R], axis=1)
fade_in = np.minimum(np.arange(N) / (0.25 * SR), 1.0)
fade_out = np.minimum((N - np.arange(N)) / (1.6 * SR), 1.0)
out *= (fade_in * fade_out)[:, None]
out = np.tanh(out * 1.25)
out = out / np.max(np.abs(out)) * 0.89
pcm = (out * 32767).astype("<i2")

path = sys.argv[1] if len(sys.argv) > 1 else "music.wav"
with wave.open(path, "wb") as w:
    w.setnchannels(2)
    w.setsampwidth(2)
    w.setframerate(SR)
    w.writeframes(pcm.tobytes())
print("wrote", path, f"{N / SR:.1f}s")

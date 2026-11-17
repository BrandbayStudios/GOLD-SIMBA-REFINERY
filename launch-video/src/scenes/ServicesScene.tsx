import React from "react";
import { AbsoluteFill, Img, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";
import { C, F } from "../theme";

const PANELS = [
  { img: "plumbing.webp", t: "Plumbing" },
  { img: "electrical.webp", t: "Electrical" },
  { img: "electrical-solar.webp", t: "Solar" },
];

export const ServicesScene: React.FC = () => {
  const frame = useCurrentFrame(); // 90
  const { fps } = useVideoConfig();
  return (
    <AbsoluteFill style={{ background: C.navy, flexDirection: "row", padding: "180px 40px 40px", gap: 30 }}>
      {PANELS.map((p, i) => {
        const s = spring({ frame: frame - i * 6, fps, config: { damping: 16 } });
        const zoom = interpolate(frame, [0, 90], [1.0, 1.15]);
        return (
          <div key={p.t} style={{ flex: 1, position: "relative", borderRadius: 48, overflow: "hidden", opacity: s, transform: `translateY(${(1 - s) * 120}px)` }}>
            <Img src={staticFile(`img/${p.img}`)} style={{ width: "100%", height: "100%", objectFit: "cover", transform: `scale(${zoom})` }} />
            <div style={{ position: "absolute", inset: 0, background: "linear-gradient(to top, rgba(16,29,53,.9), rgba(16,29,53,0) 55%)" }} />
            <div style={{ position: "absolute", left: 44, bottom: 52, fontFamily: F.head, fontWeight: 800, fontSize: 84, color: C.white }}>
              {p.t}<div style={{ width: 90, height: 8, borderRadius: 4, background: C.orange, marginTop: 14 }} />
            </div>
          </div>
        );
      })}
      <div style={{ position: "absolute", top: 50, left: 0, right: 0, textAlign: "center", fontFamily: F.head, fontWeight: 800, fontSize: 72, color: C.white }}>
        And many more services
      </div>
    </AbsoluteFill>
  );
};

import React from "react";
import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { C, F } from "../theme";
import { Logo } from "../Logo";

export const CtaScene: React.FC = () => {
  const frame = useCurrentFrame(); // 90
  const { fps } = useVideoConfig();
  const a = spring({ frame, fps, config: { damping: 14 } });
  const b = spring({ frame: frame - 14, fps, config: { damping: 16 } });
  const c = spring({ frame: frame - 28, fps, config: { damping: 16 } });
  const fade = interpolate(frame, [76, 90], [1, 0], { extrapolateLeft: "clamp" });
  return (
    <AbsoluteFill style={{ background: `radial-gradient(circle at 50% 35%, ${C.navy2}, ${C.navy})`, alignItems: "center", justifyContent: "center", opacity: fade }}>
      <div style={{ transform: `scale(${0.6 + 0.4 * a})`, opacity: a, marginBottom: 60 }}><Logo size={120} /></div>
      <div style={{ fontFamily: F.head, fontWeight: 800, fontSize: 130, color: C.white, letterSpacing: -3, opacity: b, transform: `translateY(${(1 - b) * 40}px)` }}>
        Help is <span style={{ color: C.orange }}>one tap</span> away.
      </div>
      <div style={{ display: "flex", gap: 30, marginTop: 56, opacity: c, transform: `translateY(${(1 - c) * 40}px)` }}>
        <div style={{ background: C.orange, color: C.navy, fontFamily: F.head, fontWeight: 800, fontSize: 44, padding: "26px 60px", borderRadius: 60 }}>Request help</div>
        <div style={{ border: `4px solid ${C.white}`, color: C.white, fontFamily: F.head, fontWeight: 800, fontSize: 44, padding: "22px 56px", borderRadius: 60 }}>Become a provider</div>
      </div>
      <div style={{ marginTop: 56, fontFamily: F.body, fontWeight: 700, fontSize: 42, color: C.white, opacity: c, textAlign: "center", lineHeight: 1.5 }}>
        handynow.tiltsolutions.tech<br /><span style={{ color: C.orange }}>+260 972 015 689</span>
      </div>
    </AbsoluteFill>
  );
};

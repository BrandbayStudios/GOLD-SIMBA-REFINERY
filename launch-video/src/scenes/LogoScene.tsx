import React from "react";
import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { C, F } from "../theme";
import { Logo } from "../Logo";

export const LogoScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const s = spring({ frame, fps, config: { damping: 12, stiffness: 120 } });
  const tag = interpolate(frame, [30, 50], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const ring = interpolate(frame, [0, 45], [0.2, 2.2], { extrapolateRight: "clamp" });
  return (
    <AbsoluteFill style={{ background: C.navy, alignItems: "center", justifyContent: "center" }}>
      <div style={{ position: "absolute", width: 700, height: 700, borderRadius: 350, border: `4px solid ${C.orange}`, opacity: Math.max(0, 1 - ring / 2.2), transform: `scale(${ring})` }} />
      <div style={{ transform: `scale(${0.4 + 0.6 * s})`, opacity: Math.min(1, frame / 6) }}>
        <Logo size={170} />
      </div>
      <div style={{ position: "absolute", top: 640, fontFamily: F.body, fontWeight: 700, fontSize: 60, color: C.white, opacity: tag, transform: `translateY(${(1 - tag) * 24}px)`, letterSpacing: 1 }}>
        Emergency Services. <span style={{ color: C.orange }}>One Call Away.</span>
      </div>
      <div style={{ position: "absolute", bottom: 90, fontFamily: F.body, fontWeight: 600, fontSize: 30, color: C.mid, opacity: tag, letterSpacing: 6 }}>ZAMBIA · ZMW</div>
    </AbsoluteFill>
  );
};

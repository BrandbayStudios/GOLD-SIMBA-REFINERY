import React from "react";
import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { C, F } from "../theme";
import { Icon } from "../Icon";

const CARDS = [
  { t: "Burst pipe.", icon: "droplet", col: "#38BDF8" },
  { t: "Dead battery.", icon: "battery", col: C.orange },
  { t: "Power cut.", icon: "bolt", col: "#FACC15" },
];

export const HookScene: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const idx = Math.min(2, Math.floor(frame / 25));
  const local = frame - idx * 25;
  const card = CARDS[idx];
  const showQ = frame >= 78;
  const s = spring({ frame: local, fps, config: { damping: 14, stiffness: 180 } });
  const qs = spring({ frame: frame - 78, fps, config: { damping: 16 } });
  const pulse = 1 + 0.04 * Math.sin(frame / 3);
  return (
    <AbsoluteFill style={{ background: `radial-gradient(circle at 50% 40%, ${C.navy2}, ${C.navy})`, alignItems: "center", justifyContent: "center" }}>
      {!showQ && (
        <div style={{ transform: `scale(${0.7 + 0.3 * s})`, opacity: Math.min(1, local / 4), textAlign: "center" }}>
          <div style={{ width: 220, height: 220, borderRadius: 60, background: card.col + "22", border: `3px solid ${card.col}`, margin: "0 auto 50px", display: "flex", alignItems: "center", justifyContent: "center", transform: `scale(${pulse})` }}>
            <Icon name={card.icon} size={120} color={card.col} stroke={1.8} />
          </div>
          <div style={{ fontFamily: F.head, fontWeight: 800, fontSize: 170, color: C.white, letterSpacing: -4 }}>{card.t}</div>
        </div>
      )}
      {showQ && (
        <div style={{ textAlign: "center", transform: `scale(${0.85 + 0.15 * qs})`, opacity: interpolate(frame, [78, 86], [0, 1], { extrapolateRight: "clamp" }) }}>
          <div style={{ fontFamily: F.head, fontWeight: 800, fontSize: 190, color: C.white, letterSpacing: -4 }}>
            Who do you <span style={{ color: C.orange }}>call?</span>
          </div>
          <div style={{ marginTop: 30, display: "flex", justifyContent: "center", gap: 14 }}>
            {[0, 1, 2].map((i) => (
              <div key={i} style={{ width: 18, height: 18, borderRadius: 9, background: C.orange, opacity: 0.35 + 0.65 * Math.abs(Math.sin((frame - i * 5) / 6)) }} />
            ))}
          </div>
        </div>
      )}
    </AbsoluteFill>
  );
};

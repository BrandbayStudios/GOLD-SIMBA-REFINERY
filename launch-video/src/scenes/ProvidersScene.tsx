import React from "react";
import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { C, F } from "../theme";
import { Icon } from "../Icon";

const PROS = [
  { n: "Chanda M.", i: "CM", d: "12 min away", r: "4.9", p: "Fixed price K350", hi: true },
  { n: "Mwansa T.", i: "MT", d: "18 min away", r: "4.8", p: "Quote K380", hi: false },
  { n: "Bupe K.", i: "BK", d: "25 min away", r: "4.7", p: "Quote K420", hi: false },
];

export const ProvidersScene: React.FC = () => {
  const frame = useCurrentFrame(); // 180
  const { fps } = useVideoConfig();
  const head = spring({ frame, fps, config: { damping: 18 } });
  const badges = interpolate(frame, [95, 115], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  return (
    <AbsoluteFill style={{ background: C.grey }}>
      <div style={{ position: "absolute", top: 90, left: 0, right: 0, textAlign: "center", opacity: head, transform: `translateY(${(1 - head) * -30}px)` }}>
        <div style={{ fontFamily: F.head, fontWeight: 800, fontSize: 84, color: C.navy, letterSpacing: -2 }}>
          Verified providers. <span style={{ color: C.orange }}>Clear prices.</span>
        </div>
      </div>
      <div style={{ position: "absolute", top: 260, left: 360, right: 360 }}>
        {PROS.map((p, i) => {
          const s = spring({ frame: frame - 14 - i * 12, fps, config: { damping: 15, stiffness: 140 } });
          const glow = p.hi ? 1 + 0.015 * Math.sin(frame / 5) : 1;
          return (
            <div key={p.n} style={{
              display: "flex", alignItems: "center", gap: 34, background: C.white, borderRadius: 34, padding: "30px 40px", marginBottom: 28,
              border: p.hi ? `4px solid ${C.orange}` : "4px solid transparent",
              boxShadow: p.hi ? "0 20px 60px rgba(255,138,36,.28)" : "0 10px 30px rgba(16,29,53,.08)",
              opacity: s, transform: `translateX(${(1 - s) * 260}px) scale(${glow})`,
            }}>
              <div style={{ width: 110, height: 110, borderRadius: 55, background: C.navy, color: C.white, display: "flex", alignItems: "center", justifyContent: "center", fontFamily: F.head, fontWeight: 800, fontSize: 42 }}>{p.i}</div>
              <div style={{ flex: 1 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
                  <span style={{ fontFamily: F.head, fontWeight: 800, fontSize: 48, color: C.navy }}>{p.n}</span>
                  <Icon name="shield" size={40} color={C.green} />
                  <span style={{ fontFamily: F.body, fontWeight: 700, fontSize: 26, color: C.green }}>Verified</span>
                </div>
                <div style={{ display: "flex", gap: 30, marginTop: 10, fontFamily: F.body, fontWeight: 600, fontSize: 30, color: C.mid, alignItems: "center" }}>
                  <span style={{ display: "flex", alignItems: "center", gap: 8 }}><Icon name="star" size={32} color={C.orange} fill={C.orange} />{p.r}</span>
                  <span style={{ display: "flex", alignItems: "center", gap: 8 }}><Icon name="pin" size={32} color={C.mid} />{p.d}</span>
                </div>
              </div>
              <div style={{ background: p.hi ? C.orange : C.grey, color: C.navy, fontFamily: F.head, fontWeight: 800, fontSize: 40, padding: "20px 34px", borderRadius: 24 }}>{p.p}</div>
            </div>
          );
        })}
      </div>
      <div style={{ position: "absolute", bottom: 100, left: 0, right: 0, display: "flex", justifyContent: "center", gap: 24, opacity: badges, transform: `translateY(${(1 - badges) * 20}px)` }}>
        {["NRC checked", "Licences checked", "Rated by customers"].map((b) => (
          <div key={b} style={{ background: C.navy, color: C.white, fontFamily: F.body, fontWeight: 700, fontSize: 32, padding: "16px 34px", borderRadius: 40, display: "flex", alignItems: "center", gap: 12 }}>
            <Icon name="check" size={32} color={C.orange} stroke={3} />{b}
          </div>
        ))}
      </div>
      <div style={{ position: "absolute", bottom: 28, left: 0, right: 0, textAlign: "center", fontFamily: F.body, fontWeight: 500, fontSize: 20, color: C.mid }}>Illustrative app screens and prices</div>
    </AbsoluteFill>
  );
};

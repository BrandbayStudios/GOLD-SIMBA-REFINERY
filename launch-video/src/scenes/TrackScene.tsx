import React from "react";
import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { getLength, getPointAtLength } from "@remotion/paths";
import { C, F, W, H } from "../theme";
import { StreetMap } from "../StreetMap";

const ROUTE = "M 230 860 C 520 860, 520 560, 820 560 S 1180 300, 1500 340 S 1680 300, 1700 260";
const LEN = getLength(ROUTE);

export const TrackScene: React.FC = () => {
  const frame = useCurrentFrame(); // 150
  const { fps } = useVideoConfig();
  const prog = interpolate(frame, [10, 120], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const pt = getPointAtLength(ROUTE, LEN * prog) ?? { x: 230, y: 860 };
  const eta = Math.max(1, Math.round(12 * (1 - prog)));
  const card = spring({ frame: frame - 6, fps, config: { damping: 16 } });
  const chips = spring({ frame: frame - 70, fps, config: { damping: 16 } });
  return (
    <AbsoluteFill style={{ background: C.navy }}>
      <StreetMap w={W} h={H} seed={21} dark />
      <svg width={W} height={H} style={{ position: "absolute", inset: 0 }}>
        <path d={ROUTE} fill="none" stroke="rgba(255,138,36,.25)" strokeWidth={14} strokeLinecap="round" />
        <path d={ROUTE} fill="none" stroke={C.orange} strokeWidth={14} strokeLinecap="round" strokeDasharray={`${LEN * prog} ${LEN}`} />
        <circle cx={1700} cy={260} r={20} fill={C.white} stroke={C.orange} strokeWidth={8} />
        <circle cx={pt.x} cy={pt.y} r={44} fill={C.orange} opacity={0.25 + 0.15 * Math.sin(frame / 4)} />
        <circle cx={pt.x} cy={pt.y} r={26} fill={C.orange} stroke={C.white} strokeWidth={6} />
      </svg>
      <div style={{ position: "absolute", left: 90, top: 80, width: 640, background: C.white, borderRadius: 36, padding: "34px 40px", boxShadow: "0 30px 80px rgba(0,0,0,.4)", opacity: card, transform: `translateY(${(1 - card) * -40}px)` }}>
        <div style={{ fontFamily: F.body, fontWeight: 700, fontSize: 28, color: C.mid }}>Chanda M. is on the way</div>
        <div style={{ display: "flex", alignItems: "baseline", gap: 14, marginTop: 6 }}>
          <span style={{ fontFamily: F.head, fontWeight: 800, fontSize: 130, color: C.navy, lineHeight: 1 }}>{eta}</span>
          <span style={{ fontFamily: F.head, fontWeight: 700, fontSize: 44, color: C.orange }}>min away</span>
        </div>
      </div>
      <div style={{ position: "absolute", left: 0, right: 0, bottom: 80, display: "flex", justifyContent: "center", alignItems: "center", gap: 22, opacity: chips, transform: `translateY(${(1 - chips) * 40}px)` }}>
        <span style={{ fontFamily: F.head, fontWeight: 800, fontSize: 40, color: C.white, marginRight: 10 }}>Pay your way</span>
        {[["MTN MoMo", "#FFCB05", C.navy], ["Airtel Money", "#E40000", C.white], ["Zamtel Kwacha", "#00A651", C.white]].map(([t, bg, fg]) => (
          <div key={t} style={{ background: bg, color: fg, fontFamily: F.head, fontWeight: 800, fontSize: 36, padding: "18px 36px", borderRadius: 40 }}>{t}</div>
        ))}
      </div>
      <div style={{ position: "absolute", bottom: 20, left: 0, right: 0, textAlign: "center", fontFamily: F.body, fontWeight: 500, fontSize: 18, color: "rgba(255,255,255,.4)" }}>Illustrative app screens</div>
    </AbsoluteFill>
  );
};

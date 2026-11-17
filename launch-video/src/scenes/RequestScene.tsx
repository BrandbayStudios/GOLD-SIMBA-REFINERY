import React from "react";
import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { C, F } from "../theme";
import { Icon } from "../Icon";
import { Phone, Disclaimer } from "./Phone";
import { StreetMap } from "../StreetMap";

const CATS = [["truck", "Roadside"], ["bolt", "Electrical"], ["droplet", "Plumbing"], ["flame", "Gas"], ["cog", "Appliance"], ["hammer", "Carpentry"]];
const STEPS = ["Pick your emergency", "Share your location", "Send the request"];

export const RequestScene: React.FC = () => {
  const frame = useCurrentFrame(); // 180 frames
  const { fps } = useVideoConfig();
  const step = frame < 60 ? 0 : frame < 120 ? 1 : 2;
  const enter = spring({ frame, fps, config: { damping: 16 } });
  const typed = "Car won't start, stuck on Great East Rd".slice(0, Math.max(0, Math.floor((frame - 20) / 1.2)));
  const pinY = interpolate(frame, [62, 82], [-200, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const pinBounce = frame > 82 ? Math.max(0, 18 * Math.exp(-(frame - 82) / 5) * Math.cos((frame - 82) / 2)) : 0;
  const sent = frame >= 140;
  return (
    <AbsoluteFill style={{ background: `linear-gradient(135deg, ${C.navy}, ${C.navy2})` }}>
      <div style={{ position: "absolute", left: 150, top: 300 }}>
        {STEPS.map((t, i) => {
          const active = i === step;
          const done = i < step;
          return (
            <div key={t} style={{ display: "flex", alignItems: "center", gap: 28, marginBottom: 56, opacity: active ? 1 : 0.4, transform: `translateX(${active ? 16 : 0}px)` }}>
              <div style={{ width: 84, height: 84, borderRadius: 42, background: active || done ? C.orange : "transparent", border: `3px solid ${C.orange}`, display: "flex", alignItems: "center", justifyContent: "center", fontFamily: F.head, fontWeight: 800, fontSize: 40, color: C.navy }}>
                {done ? <Icon name="check" size={44} color={C.navy} stroke={3} /> : <span style={{ color: active ? C.navy : C.orange }}>{i + 1}</span>}
              </div>
              <div style={{ fontFamily: F.head, fontWeight: 700, fontSize: 62, color: C.white }}>{t}</div>
            </div>
          );
        })}
      </div>
      <div style={{ position: "absolute", right: 230, top: 70, transform: `translateY(${(1 - enter) * 160}px) rotate(${(1 - enter) * 4}deg)`, opacity: enter }}>
        <Phone>
          {/* screen 1 + 3 share a form; screen 2 is map */}
          {step !== 1 && (
            <div style={{ padding: "90px 32px 0" }}>
              <div style={{ fontFamily: F.head, fontWeight: 800, fontSize: 36, color: C.navy }}>What's the emergency?</div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 14, marginTop: 26 }}>
                {CATS.map(([ic, name], i) => {
                  const sel = i === 0;
                  return (
                    <div key={name} style={{ background: sel ? C.orange : C.white, borderRadius: 22, padding: "22px 0", textAlign: "center", boxShadow: "0 4px 14px rgba(16,29,53,.08)" }}>
                      <Icon name={ic} size={44} color={sel ? C.navy : C.navy2} />
                      <div style={{ fontFamily: F.body, fontWeight: 700, fontSize: 19, color: C.navy, marginTop: 6 }}>{name}</div>
                    </div>
                  );
                })}
              </div>
              <div style={{ marginTop: 28, background: C.white, borderRadius: 22, padding: 22, minHeight: 130, fontFamily: F.body, fontWeight: 500, fontSize: 25, color: C.navy, boxShadow: "0 4px 14px rgba(16,29,53,.08)" }}>
                {typed || <span style={{ color: C.mid }}>Describe the problem…</span>}
                {frame < 60 && <span style={{ color: C.orange }}>|</span>}
              </div>
              <div style={{ marginTop: 30, borderRadius: 26, padding: "26px 0", textAlign: "center", background: C.orange, fontFamily: F.head, fontWeight: 800, fontSize: 32, color: C.navy, transform: `scale(${sent ? 0.96 : 1})`, boxShadow: sent ? "none" : "0 12px 30px rgba(255,138,36,.45)" }}>
                {sent ? "Request sent ✓" : "Send emergency request"}
              </div>
            </div>
          )}
          {step === 1 && (
            <div style={{ position: "absolute", inset: 0 }}>
              <StreetMap w={440} h={900} seed={9} />
              <div style={{ position: "absolute", left: 220 - 36, top: 430 - 72 + pinY - pinBounce }}>
                <Icon name="pin" size={72} color={C.orange} fill={C.orange} stroke={1.5} />
              </div>
              <div style={{ position: "absolute", left: 24, right: 24, bottom: 40, background: C.white, borderRadius: 26, padding: 24, boxShadow: "0 10px 40px rgba(16,29,53,.2)" }}>
                <div style={{ fontFamily: F.body, fontWeight: 600, fontSize: 20, color: C.mid }}>Your location</div>
                <div style={{ fontFamily: F.head, fontWeight: 800, fontSize: 32, color: C.navy, margin: "4px 0 14px" }}>Kabulonga, Lusaka</div>
                <div style={{ display: "flex", alignItems: "center", gap: 10, fontFamily: F.body, fontWeight: 700, fontSize: 22, color: C.orange }}>
                  <Icon name="nav" size={26} color={C.orange} /> Use my location
                </div>
              </div>
            </div>
          )}
          {sent && (
            <div style={{ position: "absolute", inset: 0, background: "rgba(16,29,53,.88)", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center" }}>
              <div style={{ position: "relative", width: 260, height: 260, display: "flex", alignItems: "center", justifyContent: "center" }}>
                {[0, 1, 2].map((i) => {
                  const p = ((frame - 140 + i * 13) % 40) / 40;
                  return <div key={i} style={{ position: "absolute", width: 260, height: 260, borderRadius: 130, border: `4px solid ${C.orange}`, transform: `scale(${0.3 + p * 0.9})`, opacity: 1 - p }} />;
                })}
                <Icon name="siren" size={90} color={C.orange} />
              </div>
              <div style={{ fontFamily: F.head, fontWeight: 800, fontSize: 36, color: C.white, textAlign: "center", marginTop: 20, padding: "0 30px" }}>Providers nearby alerted</div>
            </div>
          )}
        </Phone>
      </div>
      <Disclaimer />
    </AbsoluteFill>
  );
};

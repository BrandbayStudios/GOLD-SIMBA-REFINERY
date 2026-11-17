import React from "react";
import { C, F } from "./theme";
import { Icon } from "./Icon";

export const Logo: React.FC<{ size?: number; dark?: boolean }> = ({ size = 120, dark = false }) => (
  <div style={{ display: "flex", alignItems: "center", gap: size * 0.28 }}>
    <div style={{
      width: size, height: size, borderRadius: size * 0.28, background: C.orange,
      display: "flex", alignItems: "center", justifyContent: "center",
      boxShadow: `0 ${size * 0.12}px ${size * 0.4}px rgba(255,138,36,.45)`,
    }}>
      <Icon name="bolt" size={size * 0.6} color={C.navy} stroke={2.2} fill={C.navy} />
    </div>
    <div style={{ fontFamily: F.head, fontWeight: 800, fontSize: size * 0.62, letterSpacing: -1, color: dark ? C.navy : C.white, lineHeight: 1 }}>
      HANDY<span style={{ color: C.orange }}>NOW</span>
    </div>
  </div>
);

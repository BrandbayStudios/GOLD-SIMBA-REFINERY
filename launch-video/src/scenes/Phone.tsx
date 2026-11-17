import React from "react";
import { C } from "../theme";

export const Phone: React.FC<{ children: React.ReactNode; w?: number; h?: number }> = ({ children, w = 470, h = 940 }) => (
  <div style={{ width: w, height: h, borderRadius: 70, background: "#05090F", padding: 16, boxShadow: "0 50px 120px rgba(0,0,0,.55), 0 0 0 3px #2B3B5C" }}>
    <div style={{ width: "100%", height: "100%", borderRadius: 56, overflow: "hidden", background: C.grey, position: "relative" }}>
      <div style={{ position: "absolute", top: 14, left: "50%", marginLeft: -60, width: 120, height: 32, borderRadius: 16, background: "#05090F", zIndex: 10 }} />
      {children}
    </div>
  </div>
);

export const Disclaimer: React.FC = () => (
  <div style={{ position: "absolute", bottom: 28, left: 0, right: 0, textAlign: "center", fontFamily: "Plus Jakarta Sans", fontWeight: 500, fontSize: 20, color: "rgba(255,255,255,.45)" }}>
    Illustrative app screens
  </div>
);

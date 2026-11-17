import React from "react";

function rnd(seed: number) {
  let s = seed;
  return () => ((s = (s * 1664525 + 1013904223) % 4294967296) / 4294967296);
}

export const StreetMap: React.FC<{ w: number; h: number; seed?: number; dark?: boolean }> = ({ w, h, seed = 4, dark = false }) => {
  const r = rnd(seed);
  const bg = dark ? "#16243F" : "#EAEFF3";
  const road = dark ? "#22345A" : "#FFFFFF";
  const block = dark ? "#1B2B4B" : "#DDE5EB";
  const park = dark ? "#1D3A3A" : "#CFE8D5";
  const lines: React.ReactNode[] = [];
  const blocks: React.ReactNode[] = [];
  for (let i = 0; i < 9; i++) {
    const y = (i + 0.5) * (h / 9) + (r() - 0.5) * 30;
    lines.push(<line key={"h" + i} x1={0} y1={y} x2={w} y2={y + (r() - 0.5) * 90} stroke={road} strokeWidth={i % 3 === 0 ? 18 : 9} />);
  }
  for (let i = 0; i < 8; i++) {
    const x = (i + 0.5) * (w / 8) + (r() - 0.5) * 30;
    lines.push(<line key={"v" + i} x1={x} y1={0} x2={x + (r() - 0.5) * 90} y2={h} stroke={road} strokeWidth={i % 3 === 1 ? 18 : 9} />);
  }
  for (let i = 0; i < 26; i++) {
    blocks.push(<rect key={i} x={r() * w} y={r() * h} width={50 + r() * 90} height={40 + r() * 70} rx={8} fill={i % 7 === 0 ? park : block} opacity={0.85} />);
  }
  return (
    <svg width={w} height={h} style={{ display: "block" }}>
      <rect width={w} height={h} fill={bg} />
      {blocks}
      {lines}
    </svg>
  );
};

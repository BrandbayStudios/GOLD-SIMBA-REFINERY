import React from "react";

const P: Record<string, React.ReactNode> = {
  truck: <><path d="M3 7h11v9H3z" /><path d="M14 10h4l3 3v3h-7z" /><circle cx="7" cy="18" r="2" /><circle cx="17" cy="18" r="2" /></>,
  bolt: <path d="M13 2 4 14h7l-1 8 9-12h-7z" />,
  droplet: <path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z" />,
  battery: <><rect x="2" y="7" width="17" height="10" rx="2" /><path d="M22 11v2" /><path d="M10 9l-2 3h4l-2 3" /></>,
  flame: <path d="M12 2c1 4 5 6 5 11a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-4-1-6 1-10z" />,
  cog: <><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2" /></>,
  hammer: <><path d="M14 5l5 5-3 3-5-5z" /><path d="M12 11 4 19l1 1 8-8" /></>,
  wrench: <path d="M14 6a4 4 0 0 0 5 5l-9 9a2.1 2.1 0 0 1-3-3l9-9a4 4 0 0 0-2-2z" />,
  pin: <><path d="M12 22s7-6.5 7-12a7 7 0 0 0-14 0c0 5.5 7 12 7 12z" /><circle cx="12" cy="10" r="2.5" /></>,
  shield: <><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z" /><path d="m8.5 12 2.5 2.5 4.5-5" /></>,
  star: <path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z" />,
  nav: <path d="M3 11 21 3l-8 18-2-8z" />,
  check: <path d="m5 12 5 5 9-10" />,
  phone: <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z" />,
  siren: <><path d="M7 18v-6a5 5 0 0 1 10 0v6" /><path d="M5 18h14v3H5z" /><path d="M12 2v2M4 5l1.5 1.5M20 5l-1.5 1.5" /></>,
  cross: <><circle cx="12" cy="12" r="8" /><path d="M12 8v8M8 12h8" /></>,
  sun: <><circle cx="12" cy="12" r="4" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2" /></>,
};

export const Icon: React.FC<{ name: string; size?: number; color?: string; stroke?: number; fill?: string }> = ({
  name, size = 48, color = "#fff", stroke = 2, fill = "none",
}) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill={fill} stroke={color} strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round">
    {P[name]}
  </svg>
);

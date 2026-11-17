import { loadFont } from "@remotion/fonts";
import { staticFile } from "remotion";

const defs: [string, string, string][] = [
  ["Sora", "sora-700.woff2", "700"],
  ["Sora", "sora-800.woff2", "800"],
  ["Plus Jakarta Sans", "jakarta-500.woff2", "500"],
  ["Plus Jakarta Sans", "jakarta-600.woff2", "600"],
  ["Plus Jakarta Sans", "jakarta-700.woff2", "700"],
  ["Plus Jakarta Sans", "jakarta-800.woff2", "800"],
];
export const fontsReady = Promise.all(
  defs.map(([family, file, weight]) => loadFont({ family, url: staticFile(`fonts/${file}`), weight })),
);

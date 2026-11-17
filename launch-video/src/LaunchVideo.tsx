import React from "react";
import { AbsoluteFill, Audio, Series, interpolate, staticFile, useCurrentFrame } from "remotion";
import { C, TOTAL } from "./theme";
import "./fonts";
import { HookScene } from "./scenes/HookScene";
import { LogoScene } from "./scenes/LogoScene";
import { RequestScene } from "./scenes/RequestScene";
import { ProvidersScene } from "./scenes/ProvidersScene";
import { TrackScene } from "./scenes/TrackScene";
import { ServicesScene } from "./scenes/ServicesScene";
import { CtaScene } from "./scenes/CtaScene";

const CUTS = [120, 210, 390, 570, 720, 810];

const Flash: React.FC = () => {
  const frame = useCurrentFrame();
  let o = 0;
  for (const c of CUTS) {
    if (frame >= c && frame < c + 8) o = Math.max(o, interpolate(frame, [c, c + 8], [0.9, 0]));
  }
  return <AbsoluteFill style={{ background: c0(o), pointerEvents: "none" }} />;
};
const c0 = (o: number) => `rgba(255,138,36,${o})`;

export const LaunchVideo: React.FC = () => {
  const frame = useCurrentFrame();
  const vol = interpolate(frame, [0, 8, 840, 900], [0, 0.9, 0.9, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  return (
    <AbsoluteFill style={{ background: C.navy }}>
      <Series>
        <Series.Sequence durationInFrames={120}><HookScene /></Series.Sequence>
        <Series.Sequence durationInFrames={90}><LogoScene /></Series.Sequence>
        <Series.Sequence durationInFrames={180}><RequestScene /></Series.Sequence>
        <Series.Sequence durationInFrames={180}><ProvidersScene /></Series.Sequence>
        <Series.Sequence durationInFrames={150}><TrackScene /></Series.Sequence>
        <Series.Sequence durationInFrames={90}><ServicesScene /></Series.Sequence>
        <Series.Sequence durationInFrames={90}><CtaScene /></Series.Sequence>
      </Series>
      <Flash />
      <Audio src={staticFile("music.wav")} volume={vol} />
    </AbsoluteFill>
  );
};
export const DURATION = TOTAL;

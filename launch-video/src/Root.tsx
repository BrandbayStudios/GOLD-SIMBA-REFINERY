import React from "react";
import { Composition } from "remotion";
import { LaunchVideo } from "./LaunchVideo";
import { FPS, H, TOTAL, W } from "./theme";

export const RemotionRoot: React.FC = () => (
  <Composition id="HandyNowLaunch" component={LaunchVideo} durationInFrames={TOTAL} fps={FPS} width={W} height={H} />
);

#!/bin/sh
# Starts the Craft Anything MCP server. Downloads and checksum-verifies the
# pinned Linux x86_64 CLI into ~/.local/bin on first run (cloud sessions are fresh).
set -eu
VER=0.6.1
SHA=83ec0730c9117f399122b315c63ed4c3a8d31ac55bddd74cb8b33cd2f76ef704
BIN="$HOME/.local/bin/craft-anything-cli"
if ! "$BIN" --version 2>/dev/null | grep -q " $VER\$"; then
  F="craft-anything-$VER-linux-x86_64.tar.gz"
  TMP=$(mktemp -d)
  curl -fsSL -o "$TMP/$F" "https://github.com/storytold/craft-anything/releases/download/v$VER/$F" >&2
  echo "$SHA  $TMP/$F" | sha256sum -c - >&2
  tar -xzf "$TMP/$F" -C "$TMP"
  mkdir -p "$HOME/.local/bin"
  cp "$TMP/craft-anything-$VER-linux-x86_64/bin/craft-anything-cli" "$BIN"
  rm -rf "$TMP"
fi
exec "$BIN" mcp "$@"

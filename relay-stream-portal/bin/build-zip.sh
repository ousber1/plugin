#!/usr/bin/env bash
# Build relay-stream-portal.zip with a single top-level relay-stream-portal/ folder.
# Usage: bin/build-zip.sh [output-dir]
set -euo pipefail
SRC="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$(dirname "$SRC")/dist}"
mkdir -p "$OUT"
TMP="$(mktemp -d)"
rsync -a --exclude '.git' --exclude '.DS_Store' --exclude 'node_modules' --exclude '*.zip' "$SRC/" "$TMP/relay-stream-portal/"
( cd "$TMP" && rm -f "$OUT/relay-stream-portal.zip" && zip -qr -X "$OUT/relay-stream-portal.zip" relay-stream-portal )
rm -rf "$TMP"
echo "$OUT/relay-stream-portal.zip"

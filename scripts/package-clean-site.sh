#!/usr/bin/env bash
# एउटा मात्र .zip — भित्र zip-in-zip हुँदैन। DB मा install.sql + DEPLOY.md + README.md मात्र।
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="$ROOT/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

NAME="cooperative-site-clean"
DEST="$STAGE/$NAME"
mkdir -p "$DEST"

rsync -a \
  --exclude='.git' \
  --exclude='dist' \
  --exclude='*.zip' \
  --exclude='.cursor' \
  --exclude='node_modules' \
  --exclude='.DS_Store' \
  --exclude='includes/superadmin-config.local.php' \
  "$ROOT/" "$DEST/"

rm -rf "$DEST/database"
mkdir -p "$DEST/database"
for f in install.sql DEPLOY.md README.md; do
  if [[ -f "$ROOT/database/$f" ]]; then
    cp "$ROOT/database/$f" "$DEST/database/"
  fi
done

mkdir -p "$OUT_DIR"
ZIP="$OUT_DIR/${NAME}.zip"
( cd "$STAGE" && rm -f "$ZIP" && zip -r -q "$ZIP" "$NAME" )
echo "OK: $ZIP"

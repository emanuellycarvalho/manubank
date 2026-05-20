#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/frontend"

echo "→ Compilando interface..."
npm run build --silent

echo "→ Publicando em public/..."
rm -rf "$ROOT/public/assets"
rm -f "$ROOT/public/index.html"

shopt -s nullglob
for item in "$ROOT/frontend/dist"/*; do
  name="$(basename "$item")"
  if [[ "$name" == "assets" ]]; then
    cp -R "$item" "$ROOT/public/assets"
  else
    cp -R "$item" "$ROOT/public/$name"
  fi
done

echo "✓ Interface pronta."

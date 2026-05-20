#!/usr/bin/env bash
# Modo desenvolvimento: PHP (8000) + Vite (5173). Só para quem altera código.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
STATE_DIR="$ROOT/.manubank"
mkdir -p "$STATE_DIR"

port_in_use() {
  command -v lsof >/dev/null 2>&1 && lsof -i ":$1" -sTCP:LISTEN >/dev/null 2>&1
}

if ! port_in_use 8000; then
  php -S 127.0.0.1:8000 -t public >"$STATE_DIR/php.log" 2>&1 &
  echo $! >"$STATE_DIR/php.pid"
fi

if ! port_in_use 5173; then
  (cd frontend && npm run dev -- --host 127.0.0.1 --port 5173) >"$STATE_DIR/vite.log" 2>&1 &
  echo $! >"$STATE_DIR/vite.pid"
fi

sleep 2
URL="http://localhost:5173"
[[ "$(uname -s)" == "Darwin" ]] && open "$URL" 2>/dev/null || true

echo "Modo dev: $URL (Vite + PHP). Encerre com: make stop"

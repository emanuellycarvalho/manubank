#!/usr/bin/env bash
# Um único servidor (PHP). Mantenha o terminal aberto enquanto usa o app.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PORT="${MANUBANK_PORT:-8080}"
URL="http://localhost:${PORT}"

if [[ ! -f "$ROOT/public/index.html" ]]; then
  echo "Interface não compilada. Rode: make setup"
  exit 1
fi

open_browser() {
  if [[ "$(uname -s)" == "Darwin" ]]; then
    open "$URL" 2>/dev/null || true
  elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$URL" 2>/dev/null || true
  fi
}

echo ""
echo "════════════════════════════════════════"
echo "  ManuBank"
echo "  $URL"
echo ""
echo "  Deixe ESTA janela aberta enquanto usa."
echo "  Para encerrar: Ctrl+C ou feche a janela."
echo "════════════════════════════════════════"
echo ""

(sleep 1 && open_browser) &

exec php -S "127.0.0.1:${PORT}" -t public public/router.php

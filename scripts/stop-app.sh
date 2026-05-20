#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STATE_DIR="$ROOT/.manubank"

stop_pid_file() {
  local file="$1"
  local label="$2"
  [[ ! -f "$file" ]] && return 0
  local pid
  pid="$(cat "$file" 2>/dev/null || true)"
  if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
    echo "→ Encerrando $label..."
    kill "$pid" 2>/dev/null || true
    sleep 0.3
    kill -9 "$pid" 2>/dev/null || true
  fi
  rm -f "$file"
}

stop_pid_file "$STATE_DIR/php.pid" "API"
stop_pid_file "$STATE_DIR/vite.pid" "Vite"

if command -v lsof >/dev/null 2>&1; then
  for port in 8080 8000 5173; do
    pids="$(lsof -ti ":$port" -sTCP:LISTEN 2>/dev/null || true)"
    if [[ -n "$pids" ]]; then
      echo "→ Liberando porta $port..."
      kill $pids 2>/dev/null || true
    fi
  done
fi

echo "✓ Servidores encerrados."

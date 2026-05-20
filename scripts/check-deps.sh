#!/usr/bin/env bash
# Verifica se PHP, Composer, Node e npm estão instalados.

set -euo pipefail

missing=0

check_cmd() {
  local cmd="$1"
  local label="$2"
  local hint="$3"

  if command -v "$cmd" >/dev/null 2>&1; then
    return 0
  fi

  echo "✗ Falta: $label ($cmd)"
  echo "  $hint"
  missing=1
}

echo "Verificando o que precisa para rodar o ManuBank..."
echo ""

check_cmd php "PHP" "No Mac: brew install php"
check_cmd composer "Composer" "No Mac: brew install composer"
check_cmd node "Node.js" "No Mac: brew install node"
check_cmd npm "npm" "Vem junto com o Node.js"
check_cmd make "Make" "No Mac: xcode-select --install (Ferramentas de linha de comando)"

if [[ "$missing" -ne 0 ]]; then
  echo ""
  echo "Instale os itens acima e rode de novo: make app"
  exit 1
fi

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
echo "✓ PHP $PHP_VER"
echo "✓ Composer $(composer --version 2>/dev/null | head -1)"
echo "✓ Node $(node --version)"
echo "✓ npm $(npm --version)"
echo ""

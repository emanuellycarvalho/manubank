#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

bash "$ROOT/scripts/check-deps.sh"

echo "→ Instalando dependências PHP (Composer)..."
composer install --no-interaction

echo "→ Instalando dependências da interface (npm)..."
(cd frontend && npm install)

echo "→ Criando / atualizando base de dados..."
php src/db/init_db.php

echo "→ Carregando categorias e regras iniciais..."
php src/db/seeder_categories.php
php src/db/seeder_rules.php

bash "$ROOT/scripts/build-frontend.sh"

echo ""
echo "  ✓ Preparação concluída."
echo ""

# ManuBank — uso local
# Windows: clique duplo em app.bat  |  Mac/Linux: make app

.DEFAULT_GOAL := help

PORT ?= 8080
export MANUBANK_PORT := $(PORT)

.PHONY: help app setup build dev stop check

help:
	@echo ""
	@echo "  ManuBank"
	@echo ""
	@echo "    make app      Prepara e abre (1 servidor, porta $(PORT))"
	@echo "    make setup    Só instala / compila (sem abrir)"
	@echo "    make dev      Modo programador (Vite + PHP)"
	@echo "    make stop     Encerra servidores em segundo plano"
	@echo ""
	@echo "  Windows: use app.bat (instala Node/PHP automaticamente)"
	@echo ""

app: setup
	@bash scripts/run-prod.sh

setup: check
	@bash scripts/setup.sh

build:
	@bash scripts/build-frontend.sh

dev: setup
	@bash scripts/run-dev.sh

check:
	@bash scripts/check-deps.sh

stop:
	@bash scripts/stop-app.sh

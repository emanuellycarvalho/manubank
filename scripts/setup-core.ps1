# Dependencias, banco, seeds e build da interface.

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

Write-Host "-> Dependencias PHP (Composer)..."
composer install --no-interaction

Write-Host "-> Dependencias da interface (npm)..."
Push-Location (Join-Path $Root "frontend")
npm install --silent
Pop-Location

Write-Host "-> Base de dados..."
php src/db/init_db.php

Write-Host "-> Dados iniciais..."
php src/db/seeder_categories.php
php src/db/seeder_rules.php

& (Join-Path $Root "scripts\build-frontend.ps1")

Write-Host ""
Write-Host "Preparacao concluida."
Write-Host ""

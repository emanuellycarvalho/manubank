# Entrada principal no Windows (chamado por app.bat).

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

Write-Host ""
Write-Host "  ManuBank - iniciando..."
Write-Host ""

& "$Root\scripts\install-deps.ps1"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& "$Root\scripts\setup-core.ps1"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& "$Root\scripts\run-prod.ps1"

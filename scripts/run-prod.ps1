# Um único servidor (PHP). Mantenha a janela aberta enquanto usa o app.

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

$Port = if ($env:MANUBANK_PORT) { $env:MANUBANK_PORT } else { "8080" }
$Url = "http://localhost:$Port"

if (-not (Test-Path (Join-Path $Root "public\index.html"))) {
    Write-Host "Interface nao compilada. Rode app.bat de novo (primeira vez demora mais)."
    exit 1
}

Write-Host ""
Write-Host "========================================"
Write-Host "  ManuBank"
Write-Host "  $Url"
Write-Host ""
Write-Host "  Deixe ESTA janela aberta enquanto usa."
Write-Host "  Para encerrar: feche a janela ou Ctrl+C."
Write-Host "========================================"
Write-Host ""

Start-Process $Url

php -S "127.0.0.1:${Port}" -t public public/router.php

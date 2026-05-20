# Compila o frontend e copia para public/

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

Write-Host "-> Compilando interface..."
Push-Location (Join-Path $Root "frontend")
npm run build --silent
Pop-Location

Write-Host "-> Publicando em public/..."
$dist = Join-Path $Root "frontend\dist"
$public = Join-Path $Root "public"

if (Test-Path (Join-Path $public "assets")) {
    Remove-Item (Join-Path $public "assets") -Recurse -Force
}
if (Test-Path (Join-Path $public "index.html")) {
    Remove-Item (Join-Path $public "index.html") -Force
}

Get-ChildItem $dist | ForEach-Object {
    $dest = Join-Path $public $_.Name
    if ($_.PSIsContainer) {
        Copy-Item $_.FullName $dest -Recurse -Force
    } else {
        Copy-Item $_.FullName $dest -Force
    }
}

Write-Host "OK Interface pronta."

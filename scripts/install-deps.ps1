# Instala PHP, Node e Composer via winget (Windows), se ainda não existirem.

$ErrorActionPreference = "Continue"

function Refresh-Path {
    $machine = [Environment]::GetEnvironmentVariable("Path", "Machine")
    $user = [Environment]::GetEnvironmentVariable("Path", "User")
    $env:Path = "$machine;$user"
}

function Test-Command($name) {
    $null -ne (Get-Command $name -ErrorAction SilentlyContinue)
}

function Install-WingetPackage($id, $label) {
    if (-not (Test-Command winget)) {
        Write-Host "  winget nao encontrado. Instale $label manualmente: https://nodejs.org"
        return $false
    }
    Write-Host "  Instalando $label (pode demorar alguns minutos)..."
    winget install --id $id -e --accept-package-agreements --accept-source-agreements --silent 2>&1 | Out-Null
    Refresh-Path
    return $true
}

Write-Host ""
Write-Host "Verificando ferramentas no Windows..."
Write-Host ""

$needsRestart = $false

if (-not (Test-Command php)) {
    if (Install-WingetPackage "PHP.PHP.8.3" "PHP") { $needsRestart = $true }
}
if (-not (Test-Command node)) {
    if (Install-WingetPackage "OpenJS.NodeJS.LTS" "Node.js") { $needsRestart = $true }
}
if (-not (Test-Command npm)) {
    if (Install-WingetPackage "OpenJS.NodeJS.LTS" "npm (via Node.js)") { $needsRestart = $true }
}
if (-not (Test-Command composer)) {
    if (Install-WingetPackage "Composer.Composer" "Composer") { $needsRestart = $true }
}

Refresh-Path

$missing = @()
if (-not (Test-Command php)) { $missing += "PHP" }
if (-not (Test-Command node)) { $missing += "Node.js" }
if (-not (Test-Command npm)) { $missing += "npm" }
if (-not (Test-Command composer)) { $missing += "Composer" }

if ($missing.Count -gt 0) {
    Write-Host ""
    Write-Host "Nao foi possivel instalar automaticamente: $($missing -join ', ')"
    Write-Host "Feche e abra um NOVO PowerShell, ou instale manualmente:"
    Write-Host "  https://nodejs.org   https://getcomposer.org   https://windows.php.net"
    Write-Host ""
    exit 1
}

Write-Host "OK PHP, Node, npm e Composer disponiveis."
if ($needsRestart) {
    Write-Host "(Se o proximo passo falhar, feche o terminal e rode app.bat de novo.)"
}
Write-Host ""

exit 0

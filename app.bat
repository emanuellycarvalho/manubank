@echo off
title ManuBank
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\app.ps1"
if errorlevel 1 (
  echo.
  echo Algo deu errado. Leia a mensagem acima.
  pause
)

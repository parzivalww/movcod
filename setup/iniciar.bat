@echo off
title Divulga Pro - Iniciando...
color 0A

echo.
echo  ==========================================
echo   DIVULGA PRO - INICIANDO SERVICOS
echo  ==========================================
echo.

:: Inicia o Laragon se nao estiver rodando
tasklist /FI "IMAGENAME eq laragon.exe" 2>nul | find /I "laragon.exe" >nul
if %errorLevel% neq 0 (
    echo [1/3] Iniciando Laragon...
    start "" "C:\laragon\laragon.exe"
    timeout /t 4 /nobreak >nul
) else (
    echo [1/3] Laragon ja esta rodando. OK
)

:: Inicia n8n em nova janela
echo [2/3] Iniciando n8n...
start "n8n" cmd /k "echo n8n iniciando... && n8n start"
timeout /t 3 /nobreak >nul

:: Inicia Cloudflare Tunnel
echo [3/3] Iniciando Cloudflare Tunnel...
echo.
echo  Aguarde a URL publica aparecer abaixo:
echo  (exemplo: https://xxxxx.trycloudflare.com)
echo.
C:\cloudflared\cloudflared.exe tunnel --url http://localhost/divulga-pro

pause

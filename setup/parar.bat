@echo off
title Parando servicos...
echo Parando todos os servicos Divulga Pro...

taskkill /F /IM cloudflared.exe >nul 2>&1
taskkill /F /IM node.exe >nul 2>&1
echo  n8n e cloudflared parados.

tasklist /FI "IMAGENAME eq laragon.exe" 2>nul | find /I "laragon.exe" >nul
if %errorLevel% == 0 (
    taskkill /F /IM laragon.exe >nul 2>&1
    taskkill /F /IM nginx.exe >nul 2>&1
    taskkill /F /IM php-cgi.exe >nul 2>&1
    echo  Laragon parado.
)

echo.
echo Todos os servicos foram encerrados.
pause

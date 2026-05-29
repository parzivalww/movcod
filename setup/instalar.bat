@echo off
title Instalador Divulga Pro
color 0A
echo.
echo  ============================================
echo   INSTALADOR AUTOMATICO - DIVULGA PRO
echo   Node.js + n8n + Cloudflared + Laragon
echo  ============================================
echo.

:: Verifica se esta rodando como Administrador
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERRO] Execute este arquivo como Administrador!
    echo Clique com botao direito -^> "Executar como administrador"
    pause
    exit /b 1
)

:: ============================================
:: PASSO 1 - LARAGON
:: ============================================
echo [1/4] Verificando Laragon...
if exist "C:\laragon\laragon.exe" (
    echo  OK - Laragon ja instalado.
) else (
    echo  Baixando Laragon...
    powershell -Command "Invoke-WebRequest -Uri 'https://github.com/nicholasgasior/laragon/releases/latest/download/laragon-wamp.exe' -OutFile '%TEMP%\laragon-setup.exe'"
    echo  Instalando Laragon ^(siga o assistente^)...
    start /wait "" "%TEMP%\laragon-setup.exe"
    echo  Laragon instalado!
)

:: ============================================
:: PASSO 2 - NODE.JS
:: ============================================
echo.
echo [2/4] Verificando Node.js...
node --version >nul 2>&1
if %errorLevel% == 0 (
    echo  OK - Node.js ja instalado:
    node --version
) else (
    echo  Instalando Node.js via winget...
    winget install OpenJS.NodeJS.LTS --silent --accept-package-agreements --accept-source-agreements
    if %errorLevel% neq 0 (
        echo  winget falhou. Baixando instalador manual...
        powershell -Command "Invoke-WebRequest -Uri 'https://nodejs.org/dist/lts/node-v20-x64.msi' -OutFile '%TEMP%\nodejs.msi'"
        msiexec /i "%TEMP%\nodejs.msi" /quiet /norestart
    )
    echo  Node.js instalado!
    :: Recarrega PATH
    call RefreshEnv.cmd 2>nul
    set "PATH=%PATH%;C:\Program Files\nodejs"
)

:: ============================================
:: PASSO 3 - N8N
:: ============================================
echo.
echo [3/4] Instalando n8n...
n8n --version >nul 2>&1
if %errorLevel% == 0 (
    echo  OK - n8n ja instalado:
    n8n --version
) else (
    echo  Instalando n8n globalmente...
    call npm install -g n8n
    echo  n8n instalado!
)

:: ============================================
:: PASSO 4 - CLOUDFLARED
:: ============================================
echo.
echo [4/4] Instalando cloudflared...
if exist "C:\cloudflared\cloudflared.exe" (
    echo  OK - cloudflared ja instalado.
) else (
    echo  Baixando cloudflared...
    mkdir "C:\cloudflared" 2>nul
    powershell -Command "Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile 'C:\cloudflared\cloudflared.exe'"
    echo  cloudflared instalado em C:\cloudflared\
)

:: ============================================
:: COPIAR PROJETO PARA LARAGON
:: ============================================
echo.
echo Copiando projeto para o Laragon...
if exist "C:\laragon\www\" (
    mkdir "C:\laragon\www\divulga-pro" 2>nul
    xcopy /E /Y /I "%~dp0..\*" "C:\laragon\www\divulga-pro\" /EXCLUDE:%~dp0xcopy-exclude.txt
    echo  Projeto copiado para C:\laragon\www\divulga-pro\
    mkdir "C:\laragon\www\divulga-pro\data" 2>nul
) else (
    echo  Laragon nao encontrado em C:\laragon\. Copie o projeto manualmente depois.
)

:: ============================================
:: CRIAR ATALHO INICIAR.BAT
:: ============================================
echo.
echo Criando atalho na area de trabalho...
set "DESKTOP=%USERPROFILE%\Desktop"
copy /Y "%~dp0iniciar.bat" "%DESKTOP%\Iniciar Divulga Pro.bat" >nul
echo  Atalho criado na area de trabalho!

echo.
echo  ============================================
echo   INSTALACAO CONCLUIDA!
echo  ============================================
echo.
echo  Proximos passos:
echo  1. Abra o Laragon e clique em "Iniciar Tudo"
echo  2. Acesse http://localhost/divulga-pro
echo  3. Use o atalho "Iniciar Divulga Pro" na area de trabalho
echo     para ligar tudo de uma vez com o Cloudflare Tunnel
echo.
pause

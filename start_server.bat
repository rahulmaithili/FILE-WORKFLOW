@echo off
title Office File Management CRM Launcher
cd /d "%~dp0"

echo =======================================================
echo    🚀 Starting PHP Database Server...
echo =======================================================
start "CRM PHP Server" cmd /c "node runner.js"

echo.
echo =======================================================
echo    🌐 Starting Public Cloudflare Tunnel...
echo =======================================================
echo.
if exist "cloudflared.exe" (
    echo Starting tunnel... The public link ending in .trycloudflare.com will appear in the new window.
    start "Cloudflare Tunnel" cmd /k "cloudflared.exe tunnel --url http://localhost:8000"
) else (
    echo [WARNING] 'cloudflared.exe' not found in this project folder.
    echo Please download cloudflared.exe and copy/paste it into this folder:
    echo "%~dp0"
    echo then run this start_server.bat file again to start the tunnel.
    echo.
)
pause

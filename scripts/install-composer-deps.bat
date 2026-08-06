@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-composer-deps.ps1"
exit /b %ERRORLEVEL%

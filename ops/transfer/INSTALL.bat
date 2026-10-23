@echo off
setlocal

rem  POSO Violation Monitoring System - one-click installer.
rem  Double-click this file on the machine that should receive the portal.
rem  Laragon must be installed and started (Start All) first.

title POSO Portal - Installer

if not exist "%~dp0Install-Portal.ps1" (
    echo.
    echo   Install-Portal.ps1 is missing from this folder.
    echo   Extract the whole ZIP, then run INSTALL.bat from the extracted folder.
    echo.
    pause
    exit /b 1
)

rem  Files that came out of a downloaded ZIP carry the mark of the web,
rem  which makes PowerShell refuse them. Clear it before running.
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-ChildItem -LiteralPath '%~dp0' -Filter *.ps1 | Unblock-File" >nul 2>&1

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Install-Portal.ps1" %*
set EXITCODE=%ERRORLEVEL%

echo.
if not "%EXITCODE%"=="0" (
    echo   The installer stopped with errors. Read the messages above.
) else (
    echo   You can close this window.
)
echo.
pause
exit /b %EXITCODE%

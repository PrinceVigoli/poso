@echo off
setlocal

rem  POSO Violation Monitoring System.
rem  Run this on a machine where the project folder has ALREADY been copied.
rem  It repairs the configuration for this machine and loads the database.
rem
rem  Laragon must be installed and started (Start All) first.

title POSO Portal - Setup

if not exist "%~dp0Setup-Here.ps1" (
    echo.
    echo   Setup-Here.ps1 is missing from this folder.
    echo   It belongs next to this file, in ops\transfer\.
    echo.
    pause
    exit /b 1
)

rem  Files that arrived over a network or out of a ZIP carry the mark of the
rem  web, which makes PowerShell refuse to run them.
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-ChildItem -LiteralPath '%~dp0' -Filter *.ps1 | Unblock-File" >nul 2>&1

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Setup-Here.ps1" %*
set EXITCODE=%ERRORLEVEL%

echo.
if not "%EXITCODE%"=="0" (
    echo   Setup stopped with errors. Read the messages above.
) else (
    echo   You can close this window.
)
echo.
pause
exit /b %EXITCODE%

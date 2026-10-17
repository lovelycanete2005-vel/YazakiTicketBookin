@echo off
setlocal

set "PROJECT_DIR=%~dp0"
set "XAMPP_DIR=C:\xampp"

if not exist "%XAMPP_DIR%\php\php.exe" (
    echo XAMPP PHP was not found at %XAMPP_DIR%\php\php.exe
    echo Install XAMPP or update XAMPP_DIR in this file.
    pause
    exit /b 1
)

if exist "%XAMPP_DIR%\xampp_start.exe" start "" "%XAMPP_DIR%\xampp_start.exe"

echo Starting Ticketly at http://127.0.0.1:8081/
echo Keep this window open while using the site.
"%XAMPP_DIR%\php\php.exe" -S 127.0.0.1:8081 -t "%PROJECT_DIR%"
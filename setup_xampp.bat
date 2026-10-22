@echo off
echo ========================================================
echo   MYFINANCE — Sinkronisasi ke XAMPP htdocs\myfinance
echo ========================================================
echo.
set TARGET_DIR=C:\xampp\htdocs\myfinance

if not exist "C:\xampp\htdocs" (
    echo [ERROR] Folder C:\xampp\htdocs tidak ditemukan!
    echo Pastikan XAMPP telah terinstal di komputer Anda di drive C:\.
    echo.
    pause
    exit /b
)

if not exist "%TARGET_DIR%" (
    echo Membuat folder target: %TARGET_DIR% ...
    mkdir "%TARGET_DIR%"
)

echo Menyalin file proyek MYFINANCE ke %TARGET_DIR% ...
xcopy /E /I /Y /Q * "%TARGET_DIR%\"

echo.
echo ========================================================
echo   SELESAI! Proyek berhasil disalin ke:
echo   %TARGET_DIR%
echo.
echo   Buka browser Anda dan akses:
echo   http://localhost/myfinance/
echo ========================================================
echo.
pause


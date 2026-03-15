@echo off
setlocal enabledelayedexpansion

REM Find PHP executable
set PHP_PATH=
for /f "delims=" %%i in ('where php 2^>nul') do (
    set PHP_PATH=%%i
    goto found_php
)

REM Try common Laragon paths
if not defined PHP_PATH (
    if exist "C:\laragon\bin\php\php-8.2.0\php.exe" (
        set PHP_PATH=C:\laragon\bin\php\php-8.2.0\php.exe
        goto found_php
    )
)

if not defined PHP_PATH (
    if exist "C:\laragon\bin\php\php-8.1.0\php.exe" (
        set PHP_PATH=C:\laragon\bin\php\php-8.1.0\php.exe
        goto found_php
    )
)

if not defined PHP_PATH (
    echo ERROR: Could not find PHP executable
    exit /b 1
)

:found_php
echo PHP found at: !PHP_PATH!
echo.

echo === File 1: includes\functions.php ===
"!PHP_PATH!" -l "c:\laragon\www\Studify\includes\functions.php"
echo.

echo === File 2: student\study_groups.php ===
"!PHP_PATH!" -l "c:\laragon\www\Studify\student\study_groups.php"
echo.

echo === File 3: student\group_messenger.php ===
"!PHP_PATH!" -l "c:\laragon\www\Studify\student\group_messenger.php"
echo.

echo === File 4: includes\header.php ===
"!PHP_PATH!" -l "c:\laragon\www\Studify\includes\header.php"
echo.

echo === LINT CHECK COMPLETE ===

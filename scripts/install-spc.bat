@echo off
setlocal enabledelayedexpansion

REM Install static-php-cli (SPC) and build phpmicro.sfx with FFI support on Windows.
REM
REM SPC builds a static PHP binary with selected extensions. The micro.sfx
REM output is a PHP interpreter stub that can be prepended to a PHAR to create
REM a standalone executable.
REM
REM Usage:
REM   scripts\install-spc.bat              Install SPC + build micro.sfx
REM   scripts\install-spc.bat --no-build   Install SPC only, skip micro.sfx build
REM
REM Prerequisites:
REM   - Windows 10 Build 17063 or later (for curl.exe)
REM   - Visual Studio 2022 with "Desktop development with C++" workload
REM     (required only for building PHP, not for using pre-built spc.exe)
REM   - 8 GB+ RAM, 20 GB+ free disk space (for PHP source compilation)

set SPC_DIR=%USERPROFILE%\.spc
set SPC_BIN=%SPC_DIR%\spc.exe
set MICRO_SFX=%SPC_DIR%\micro.sfx
set BUILD_DIR=%SPC_DIR%\build
set NO_BUILD=false

if /I "%1"=="--no-build" set NO_BUILD=true

echo === static-php-cli Installer ===
echo Target: %SPC_DIR%

REM ── Step 1: Install SPC ──

if exist "%SPC_BIN%" (
    echo [1/3] SPC already installed at %SPC_BIN%
) else (
    echo [1/3] Downloading static-php-cli...

    if not exist "%SPC_DIR%" mkdir "%SPC_DIR%"

    REM Detect architecture
    set ARCH=x86_64
    if /I "%PROCESSOR_ARCHITECTURE%"=="ARM64" set ARCH=aarch64
    if /I "%PROCESSOR_ARCHITECTURE%"=="ARM" set ARCH=aarch64

    set DOWNLOAD_URL=https://dl.static-php.dev/v3/spc-bin/nightly/spc-windows-%ARCH%.exe

    echo   Downloading: %DOWNLOAD_URL%
    curl.exe -fsSL -o "%SPC_BIN%" "%DOWNLOAD_URL%"
    if !ERRORLEVEL! neq 0 (
        echo   Download failed from: %DOWNLOAD_URL%
        echo.
        echo   Please download manually:
        echo     curl.exe -fsSL -o %%USERPROFILE%%\.spc\spc.exe ^
        echo       https://dl.static-php.dev/v3/spc-bin/nightly/spc-windows-%ARCH%.exe
        pause
        EXIT /B 1
    )
    echo   Installed: %SPC_BIN%
)

REM ── Step 2: Verify SPC ──

echo [2/3] Verifying SPC...

"%SPC_BIN%" --version 2>nul
if !ERRORLEVEL! neq 0 (
    echo   Warning: SPC version check failed (expected for very first run)
) else (
    echo   SPC binary appears valid
)

REM ── Step 3: Build micro.sfx (optional) ──

if /I "%NO_BUILD%"=="true" (
    echo [3/3] Skipped (--no-build^)
    echo.
    echo To build micro.sfx later:
    echo   %SPC_BIN% build "ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter" --build-micro
    EXIT /B 0
)

echo [3/3] Building micro.sfx with FFI support...
echo   (This downloads PHP source and compiles it — may take 10-30 minutes)
echo.
echo   IMPORTANT: Building PHP from source requires Visual Studio 2022
echo   with "Desktop development with C++" workload installed.
echo   If the build fails, install VS2022 and try again.
echo.

if exist "%MICRO_SFX%" (
    echo   micro.sfx already exists at %MICRO_SFX%
    echo   Delete it first or use --no-build to skip.
    echo   To rebuild: del "%MICRO_SFX%" ^&^& scripts\install-spc.bat
    EXIT /B 0
)

REM Build micro.sfx
"%SPC_BIN%" build "ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter" --build-micro

if !ERRORLEVEL! neq 0 (
    echo.
    echo x Build failed. Check the output above for errors.
    echo.
    echo Common issues on Windows:
    echo   - Visual Studio 2022 not installed
    echo   - "Desktop development with C++" workload missing
    echo   - PHP source download failure (check network)
    echo   - Not enough disk space (need 20 GB+)
    echo.
    pause
    EXIT /B 1
)

if exist "%MICRO_SFX%" (
    echo.
    echo. micro.sfx built successfully!
    echo   Location: %MICRO_SFX%
    for %%F in ("%MICRO_SFX%") do echo   Size: %%~zF bytes
) else (
    echo.
    echo x micro.sfx not found after build. Something went wrong.
    pause
    EXIT /B 1
)

endlocal

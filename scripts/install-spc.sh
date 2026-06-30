#!/usr/bin/env bash
#
# Install static-php-cli (SPC) and build phpmicro.sfx with FFI support.
#
# SPC builds a static PHP binary with selected extensions. The `micro.sfx`
# output is a PHP interpreter stub that can be prepended to a PHAR to create
# a standalone executable.
#
# Usage:
#   bash scripts/install-spc.sh              # Install SPC + build micro.sfx
#   bash scripts/install-spc.sh --no-build   # Install SPC only, skip micro.sfx build
#
# Prerequisites:
#   - macOS: Xcode Command Line Tools (xcode-select --install)
#   - Linux: build-essential, autoconf, bison, flex, libxml2-dev, etc.
#   - PHP 8.1+ (for running SPC itself)

set -euo pipefail

SPC_DIR="${HOME}/.spc"
SPC_BIN="${SPC_DIR}/spc"
MICRO_SFX="${SPC_DIR}/micro.sfx"
BUILD_DIR="${SPC_DIR}/build"
NO_BUILD=false

for arg in "$@"; do
    if [ "$arg" = "--no-build" ]; then
        NO_BUILD=true
    fi
done

echo "=== static-php-cli Installer ==="
echo "Target: ${SPC_DIR}"
echo ""
echo "  NOTE: If this hangs or times out, run directly instead of via composer:"
echo "    bash scripts/install-spc.sh"
echo "  (composer's process timeout of 300s may not be enough for downloads + PHP build)"
echo ""

# ── Step 1: Install SPC ──

if [ -f "${SPC_BIN}" ]; then
    # Verify the binary actually works — could be a truncated download
    if "${SPC_BIN}" --version >/dev/null 2>&1; then
        echo "[1/3] SPC already installed at ${SPC_BIN}"
    else
        echo "[1/3] SPC binary exists but is not functional (${SPC_BIN})"
        echo "  Removing and re-downloading..."
        rm -f "${SPC_BIN}"
    fi
fi

if [ ! -f "${SPC_BIN}" ]; then
    echo "[1/3] Downloading static-php-cli..."

    mkdir -p "${SPC_DIR}"

    # Detect platform
    OS="$(uname -s | tr '[:upper:]' '[:lower:]')"
    ARCH="$(uname -m)"

    # Map architecture
    case "${ARCH}" in
        x86_64|amd64) ARCH="x86_64" ;;
        aarch64|arm64) ARCH="aarch64" ;;
        *) echo "Unsupported architecture: ${ARCH}"; exit 1 ;;
    esac

    # Map OS (spc uses "macos" not "darwin")
    case "${OS}" in
        darwin) OS="macos" ;;
        linux) OS="linux" ;;
        *) echo "Unsupported OS: ${OS}"; exit 1 ;;
    esac

    # Try direct download (works well outside China)
    DOWNLOAD_URLS=(
        "https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-${OS}-${ARCH}"
        "https://dl.static-php.dev/v3/spc-bin/nightly/spc-${OS}-${ARCH}"
    )

    SPC_DOWNLOADED=false
    for url in "${DOWNLOAD_URLS[@]}"; do
        echo "  Trying: ${url}"
        if curl -fsSL --connect-timeout 10 --max-time 60 "${url}" -o "${SPC_BIN}"; then
            SPC_DOWNLOADED=true
            echo "  Downloaded from: ${url}"
            break
        fi
    done

    if [ "${SPC_DOWNLOADED}" = false ]; then
        echo "  Binary download failed. Trying git clone (v3 branch)..."

        # Detect if we're in China — if Aliyun mirror is reachable
        ALIYUN_REACHABLE=$(curl -s --connect-timeout 3 -o /dev/null -w "%{http_code}" https://mirrors.aliyun.com/composer/ 2>/dev/null || echo "000")
        IS_CHINA=false
        [ "${ALIYUN_REACHABLE}" = "200" ] && IS_CHINA=true

        SPC_BUILD_DIR="${SPC_DIR}/build-src"
        rm -rf "${SPC_BUILD_DIR}" 2>/dev/null || true
        GIT_CLONE_OK=false

        # ── Try chsrc (if available) to set git mirror transparently ──
        if command -v chsrc &>/dev/null && [ "${IS_CHINA}" = true ]; then
            echo "  chsrc detected. Setting git mirror..."
            chsrc set git --quiet 2>/dev/null || chsrc set git 2>/dev/null || true
        fi

        # ── Try ghproxy git clone (China-friendly) ──
        if [ "${IS_CHINA}" = true ] && [ "${GIT_CLONE_OK}" = false ]; then
            echo "  Trying ghproxy git clone..."
            if git clone --depth 1 --branch v3 \
                "https://ghproxy.com/https://github.com/crazywhalecc/static-php-cli.git" \
                "${SPC_BUILD_DIR}" 2>&1;
            then
                GIT_CLONE_OK=true
            else
                echo "  ghproxy failed."
            fi
        fi

        # ── Try direct git clone (may work with chsrc mirror set) ──
        if [ "${GIT_CLONE_OK}" = false ]; then
            echo "  Trying direct git clone..."
            if git clone --depth 1 --branch v3 \
                "https://github.com/crazywhalecc/static-php-cli.git" \
                "${SPC_BUILD_DIR}" 2>&1;
            then
                GIT_CLONE_OK=true
            fi
        fi

        # ── Run composer install in cloned repo ──
        if [ "${GIT_CLONE_OK}" = true ]; then
            cd "${SPC_BUILD_DIR}"
            if [ "${IS_CHINA}" = true ]; then
                echo "  Setting Aliyun Composer mirror..."
                composer config repos.packagist composer https://mirrors.aliyun.com/composer/
            fi
            echo "  Running composer install..."
            composer install --no-interaction --no-dev 2>&1 || true
            if [ -f "bin/spc" ]; then
                SPC_BIN="${SPC_BUILD_DIR}/bin/spc"
                ln -sf "${SPC_BIN}" "${SPC_DIR}/spc" 2>/dev/null || true
                echo "  SPC installed via git clone + composer."
            else
                echo "  composer install succeeded but bin/spc not found."
                SPC_BIN=""
            fi
        fi

        # ── All methods failed ──
        if [ -z "${SPC_BIN}" ] || [ ! -f "${SPC_BIN}" ]; then
            echo ""
            echo "  Error: all installation methods failed."
            echo ""
            echo "  Try manually with chsrc (recommended in China):"
            echo "    chsrc set git            # set GitHub mirror"
            echo "    chsrc set composer       # set Composer mirror"
            echo "    git clone --depth 1 --branch v3 \\"
            echo "      https://github.com/crazywhalecc/static-php-cli.git \\"
            echo "      ~/.spc/repo"
            echo "    cd ~/.spc/repo"
            echo "    composer install --no-dev"
            echo "    ln -sf ~/.spc/repo/bin/spc ~/.spc/spc"
            echo ""
            echo "  Or download SPC binary directly:"
            echo "    curl -fsSL -o ~/.spc/spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-${OS}-${ARCH}"
            echo "    chmod +x ~/.spc/spc"
            exit 1
        fi
    fi

    chmod +x "${SPC_BIN}" 2>/dev/null || true
    echo "  Installed: ${SPC_BIN}"
fi

# ── Step 2: Verify SPC ──

echo "[2/3] Verifying SPC..."

SPC_VERSION=$("${SPC_BIN}" --version 2>/dev/null || echo "unknown")
echo "  SPC version: ${SPC_VERSION}"

# ── Step 3: Build micro.sfx (optional) ──

if [ "${NO_BUILD}" = true ]; then
    echo "[3/3] Skipped (--no-build)"
    echo ""
    echo "To build micro.sfx later:"
    echo "  cd ${SPC_DIR} && ${SPC_BIN} build \"ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter\" --build-micro"
    exit 0
fi

echo "[3/3] Building micro.sfx with FFI support..."
echo "  (This downloads PHP source and compiles it — may take 10-30 minutes)"
echo ""

# Check if micro.sfx already exists
if [ -f "${MICRO_SFX}" ]; then
    echo "  micro.sfx already exists at ${MICRO_SFX}"
    echo "  Delete it first or use --no-build to skip."
    echo "  To rebuild: rm ${MICRO_SFX} && bash scripts/install-spc.sh"
    exit 0
fi

# Build micro.sfx
# SPC v3 syntax: spc build "ext1,ext2,..." --build-micro
# Output goes to buildroot/bin/micro.sfx (relative to CWD)
# We need: ffi (for libui), phar (for PHAR support), mbstring (ui2 dependency)
#           tokenizer (for nunomaduro/collision error handler),
#           filter (for symfony/console and other libs)
#
# IMPORTANT: SPC v3 uses WORKING_DIR to resolve SOURCE_PATH and DOWNLOAD_PATH.
# We must set all three env vars to point to ~/.spc/ so buildroot is created there.
#
# CRITICAL: SPC does NOT copy php-micro source into php-src/sapi/micro/ automatically.
# We must do this manually after sources are extracted, or buildconf won't find the
# micro SAPI and configure will fail with "Nothing to build."
echo "  Running: ${SPC_BIN} build \"ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter\" --build-micro"
cd "${SPC_DIR}"

# Set env vars so SPC finds sources/downloads in ~/.spc/
export WORKING_DIR="${SPC_DIR}"
export SOURCE_PATH="${SPC_DIR}/source"
export DOWNLOAD_PATH="${SPC_DIR}/downloads"

# Download sources first (if not already present)
if [ ! -d "${SOURCE_PATH}" ] || [ -z "$(ls -A "${SOURCE_PATH}" 2>/dev/null)" ]; then
    echo "  Downloading PHP source and dependencies..."
    "${SPC_BIN}" build "ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter" --build-micro --dl-parallel=8 --dl-retry=3 2>&1
else
    echo "  Sources already downloaded. Building with --no-download..."
    "${SPC_BIN}" build "ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter" --build-micro --no-download 2>&1 || {
        echo "  Build failed with --no-download. Retrying with download enabled..."
        "${SPC_BIN}" build "ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter" --build-micro --dl-parallel=8 --dl-retry=3 2>&1
    }
fi

# If build failed at configure (micro SAPI not found), manually link php-micro
if [ ! -f "${SPC_DIR}/buildroot/bin/micro.sfx" ] && [ -d "${SOURCE_PATH}/php-micro" ] && [ -d "${SOURCE_PATH}/php-src" ]; then
    echo ""
    echo "  Build may have failed because php-micro was not in php-src/sapi/micro/."
    echo "  Linking php-micro source into PHP source tree..."
    rm -rf "${SOURCE_PATH}/php-src/sapi/micro" 2>/dev/null || true
    cp -r "${SOURCE_PATH}/php-micro" "${SOURCE_PATH}/php-src/sapi/micro"
    echo "  Retrying build..."
    cd "${SPC_DIR}"
    "${SPC_BIN}" build "ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter" --build-micro --no-download 2>&1
fi

# SPC v3 outputs to buildroot/bin/micro.sfx
BUILT_MICRO="${SPC_DIR}/buildroot/bin/micro.sfx"
if [ -f "${BUILT_MICRO}" ]; then
    cp "${BUILT_MICRO}" "${MICRO_SFX}"
    rm -rf "${SPC_DIR}/buildroot" 2>/dev/null || true
    echo ""
    echo "✓ micro.sfx built successfully!"
    echo "  Location: ${MICRO_SFX}"
    echo "  Size: $(du -h "${MICRO_SFX}" | cut -f1)"
elif [ -f "${MICRO_SFX}" ]; then
    echo ""
    echo "✓ micro.sfx built successfully!"
    echo "  Location: ${MICRO_SFX}"
    echo "  Size: $(du -h "${MICRO_SFX}" | cut -f1)"
else
    echo ""
    echo "× Build failed. Check the output above for errors."
    echo ""
    echo "Common issues:"
    echo "  - Missing build dependencies (autoconf, bison, libxml2-dev, etc.)"
    echo "  - PHP source download failure (check network)"
    echo ""
    echo "On macOS, ensure Xcode Command Line Tools are installed:"
    echo "  xcode-select --install"
    echo ""
    echo "On Ubuntu/Debian:"
    echo "  sudo apt-get install build-essential autoconf bison libxml2-dev"
    exit 1
fi

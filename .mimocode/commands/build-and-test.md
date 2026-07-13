---
description: "Build PHAR + standalone binary from a PHP entry file, then smoke-test (launch, wait, kill, check crash reports). Wraps the full patch→build→test cycle."
---

# Build & Test Command

Run the complete build+test cycle for a ui2 application:

1. Find PHP 8.5 binary
2. Apply patches (`patch.php`)
3. Syntax-check the entry file
4. Build PHAR (`build-phar.php`)
5. Build standalone binary (`build-binary.php`)
6. Smoke-test: launch, wait 3s, kill, check for crash reports

## Usage

```
/build-and-test examples/tetris.php --name=Tetris
/build-and-test examples/tetris.php --name=Tetris --icon=icon.png
```

## Arguments

- `$1` — Entry PHP file (required). Example: `examples/tetris.php`
- `--name=<Name>` — App name (default: basename of entry without `.php`)
- `--icon=<path>` — Optional icon for the standalone binary

## Procedure

Execute these steps in order, stopping on any failure:

```bash
# 0. Find PHP binary
PHP=$(which php85 2>/dev/null || which php 2>/dev/null || echo "/Users/jay/Library/PhpWebStudy/alias/php85")

# 1. Apply patches
$PHP patch.php

# 2. Syntax check
$PHP -l $ENTRY

# 3. Build PHAR
$PHP -d phar.readonly=0 scripts/build-phar.php $ENTRY --out=dist/$NAME.phar

# 4. Build standalone binary
$PHP scripts/build-binary.php $ENTRY --name=$NAME --phar=dist/$NAME.phar

# 5. Smoke-test PHAR
$PHP dist/$NAME.phar &
PID=$!; sleep 3; kill $PID 2>/dev/null; wait $PID 2>/dev/null

# 6. Smoke-test standalone (macOS .app)
open dist/$NAME.app
sleep 3
pkill -f "$NAME.app/Contents/MacOS/$NAME" 2>/dev/null

# 7. Check crash reports (last 5 min)
ls -lt ~/Library/Logs/DiagnosticReports/ 2>/dev/null | grep -i "$NAME" | head -3
```

## Notes

- If `phar.readonly=1` error occurs, use `-d phar.readonly=0`
- If `micro.sfx` not found, run `scripts/install-spc.sh` first
- Crash reports appear in `~/Library/Logs/DiagnosticReports/` on macOS
- The 3-second wait is enough for GUI apps to initialize; longer for complex apps

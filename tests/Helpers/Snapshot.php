<?php

declare(strict_types=1);

/**
 * Lightweight file-based snapshot assertion helper.
 *
 * First run  → writes baseline file to tests/__snapshots__/.
 * Later runs → compares against baseline (strict JSON diff).
 *
 * To update snapshots: delete the baseline file and re-run.
 *
 * Usage:
 *   Snapshot::assert('design-tokens', $data);
 *
 * $data must be serializable via json_encode (no resources, no closures).
 */

final class Snapshot
{
    private const DIR = __DIR__ . '/../__snapshots__';

    /**
     * Assert that $actual matches the stored snapshot $name.
     *
     * @param mixed $actual Data to snapshot (will be pretty-printed as JSON).
     */
    public static function assert(string $name, mixed $actual): void
    {
        $path = self::path($name);

        if (!is_dir(self::DIR)) {
            @mkdir(self::DIR, 0755, true);
        }

        $encoded = self::encode($actual);

        if (!is_file($path)) {
            // First run — create baseline
            file_put_contents($path, $encoded);
            test()->addToAssertionCount(1);
            return;
        }

        $baseline = file_get_contents($path);
        if ($baseline === $encoded) {
            test()->addToAssertionCount(1);
            return;
        }

        // Diff output for debugging
        $basename = basename($path);
        $tmp = sys_get_temp_dir() . '/' . $basename . '.actual';
        file_put_contents($tmp, $encoded);

        $diff = self::diff($path, $tmp);
        @unlink($tmp);

        expect($encoded)->toEqual($baseline, "Snapshot mismatch: {$name}\n{$diff}");
    }

    /**
     * Update a snapshot baseline even if it already exists.
     */
    public static function update(string $name, mixed $actual): void
    {
        $path = self::path($name);
        if (!is_dir(self::DIR)) {
            @mkdir(self::DIR, 0755, true);
        }
        file_put_contents($path, self::encode($actual));
        test()->addToAssertionCount(1);
    }

    private static function path(string $name): string
    {
        // Sanitize: only alphanumeric, hyphens, underscores
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        return self::DIR . '/' . $safe . '.snap';
    }

    private static function encode(mixed $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private static function diff(string $a, string $b): string
    {
        $aEsc = escapeshellarg($a);
        $bEsc = escapeshellarg($b);

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "fc {$aEsc} {$bEsc} 2>NUL";
        } else {
            $cmd = "diff -u {$aEsc} {$bEsc} 2>/dev/null || true";
        }

        $out = shell_exec($cmd);
        return $out !== null ? $out : '(diff tool unavailable)';
    }
}

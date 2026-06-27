<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

use Utopia\System\System;

/**
 * System information utility — wraps utopia-php/system with convenience methods.
 *
 * Provides OS, CPU, memory, disk, and network info for the host machine.
 * All values are read-only snapshots captured at call time.
 *
 * ```php
 * $info = new SystemInfo();
 * echo $info->os;          // 'Linux', 'Darwin', 'WINNT'
 * echo $info->arch;        // 'arm64', 'x86_64'
 * echo $info->cpuUsage();  // 23.45 (percent)
 * echo $info->memUsed();   // 42 (percent)
 * echo $info->diskUsed();  // 65 (percent)
 * ```
 */
class SystemInfo
{
    /** Operating system name (PHP_OS_FAMILY). */
    public readonly string $os;

    /** CPU architecture. */
    public readonly string $arch;

    /** Human-readable architecture label. */
    public readonly string $archLabel;

    /** Hostname. */
    public readonly string $hostname;

    /** Number of CPU cores. */
    public readonly int $cpuCores;

    /** Total physical memory in bytes. */
    public readonly int $memTotal;

    /** Total disk space of the project directory in bytes. */
    public readonly int $diskTotal;

    /** @var list<string> Features not supported by the current OS */
    public readonly array $unsupported;

    public function __construct()
    {
        $this->os = System::getOS();
        $this->arch = System::getArch();
        try {
            $this->archLabel = System::getArchEnum();
        } catch (\Throwable) {
            // getArchEnum() regex doesn't cover Windows 'AMD64' — fall back to raw arch
            $this->archLabel = $this->arch;
        }
        try {
            $this->hostname = System::getHostname();
        } catch (\Throwable) {
            $this->hostname = php_uname('n');
        }
        try {
            $this->cpuCores = System::getCPUCores();
        } catch (\Throwable) {
            // getCPUCores() uses 'Windows' but php_uname('s') returns 'Windows NT'
            $this->cpuCores = (int) (shell_exec('echo %NUMBER_OF_PROCESSORS%') ?: 1);
        }

        $unsupported = [];
        try {
            // Utopia returns different units per OS: Darwin=MB, Linux=kB, Windows=?
            $memVal = System::getMemoryTotal();
            $this->memTotal = match ($this->os) {
                'Darwin' => $memVal * 1024 * 1024,  // MB → bytes
                'Linux'  => $memVal * 1024,          // kB → bytes
                default  => $memVal,                  // bytes
            };
        } catch (\Throwable) {
            $this->memTotal = 0;
            $unsupported[] = 'memTotal';
        }
        try {
            $this->diskTotal = System::getDiskTotal(\dirname(__DIR__, 2));
        } catch (\Throwable) {
            $this->diskTotal = 0;
            $unsupported[] = 'diskTotal';
        }
        $this->unsupported = $unsupported;
    }

    /** Current CPU usage as a percentage (0–100), or null if unsupported. */
    public function cpuUsage(int $duration = 1): ?float
    {
        try {
            return System::getCPUUsage($duration);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Free memory in bytes. */
    public function memFree(): ?int
    {
        try {
            return System::getMemoryFree();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Available memory in bytes, or null if unsupported. */
    public function memAvailable(): ?int
    {
        try {
            return System::getMemoryAvailable();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Memory usage as a percentage (0–100), or null if unsupported. */
    public function memUsed(): ?int
    {
        $avail = $this->memAvailable();
        if ($avail === null || $this->memTotal === 0) {
            return null;
        }
        return (int) \round((1 - $avail / $this->memTotal) * 100);
    }

    /** Free disk space in bytes for the project directory. */
    public function diskFree(): ?int
    {
        try {
            return System::getDiskFree(\dirname(__DIR__, 2));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Disk usage as a percentage (0–100), or null if unsupported. */
    public function diskUsed(): ?int
    {
        $free = $this->diskFree();
        if ($free === null || $this->diskTotal === 0) {
            return null;
        }
        return (int) \round((1 - $free / $this->diskTotal) * 100);
    }

    /** I/O stats (reads/writes per second), or null if unsupported. */
    public function ioUsage(int $duration = 1): ?array
    {
        try {
            return System::getIOUsage($duration);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Network usage (bytes sent/received per second), or null if unsupported. */
    public function networkUsage(int $duration = 1): ?array
    {
        try {
            return System::getNetworkUsage($duration);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Get an environment variable value. */
    public static function env(string $name, ?string $default = null): ?string
    {
        return System::getEnv($name, $default);
    }

    /** Architecture checks. */
    public function isArm(): bool   { return System::isArm(); }
    public function isX86(): bool   { return System::isX86() || str_contains($this->arch, 'x86_64') || str_contains($this->arch, 'AMD64'); }
    public function isPPC(): bool   { return System::isPPC(); }

    /** Convenience: check if ARM64/aarch64. */
    public function isArm64(): bool { return str_contains($this->arch, 'arm') || str_contains($this->arch, 'aarch'); }

    /**
     * Return all basic info as an associative array.
     */
    public function toArray(): array
    {
        $cpu = $this->cpuUsage();
        $memUsed = $this->memUsed();
        $diskUsed = $this->diskUsed();
        return [
            'os'          => $this->os,
            'arch'        => $this->arch,
            'arch_label'  => $this->archLabel,
            'hostname'    => $this->hostname,
            'cpu_cores'   => $this->cpuCores,
            'cpu_usage'   => $cpu !== null ? \round($cpu, 1) . '%' : 'N/A',
            'mem_total'   => self::fmtBytes($this->memTotal),
            'mem_used'    => $memUsed !== null ? $memUsed . '%' : 'N/A',
            'disk_total'  => self::fmtBytes($this->diskTotal),
            'disk_used'   => $diskUsed !== null ? $diskUsed . '%' : 'N/A',
        ];
    }

    /** Format bytes into a human-readable string. */
    public static function fmtBytes(int $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < \count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return \round($bytes, $precision) . ' ' . $units[$i];
    }
}

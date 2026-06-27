<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Yangweijie\Ui2\System\SystemInfo;

echo "=== System Info ===\n\n";

$info = new SystemInfo();

if ($info->unsupported) {
    echo "Unsupported on {$info->os}: " . implode(', ', $info->unsupported) . "\n\n";
}

echo "OS:          {$info->os}\n";
echo "Arch:        {$info->arch} ({$info->archLabel})\n";
echo "Hostname:    {$info->hostname}\n";
echo "CPU Cores:   {$info->cpuCores}\n";

$cpu = $info->cpuUsage();
echo "CPU Usage:   " . ($cpu !== null ? round($cpu, 1) . '%' : 'N/A') . "\n";

echo "Mem Total:   " . SystemInfo::fmtBytes($info->memTotal) . "\n";
$memUsed = $info->memUsed();
echo "Mem Used:    " . ($memUsed !== null ? $memUsed . '%' : 'N/A') . "\n";

echo "Disk Total:  " . SystemInfo::fmtBytes($info->diskTotal) . "\n";
$diskUsed = $info->diskUsed();
echo "Disk Used:   " . ($diskUsed !== null ? $diskUsed . '%' : 'N/A') . "\n\n";

echo "--- Architecture ---\n";
echo "  arm:   " . ($info->isArm() ? 'yes' : 'no') . "\n";
echo "  arm64: " . ($info->isArm64() ? 'yes' : 'no') . "\n";
echo "  x86:   " . ($info->isX86() ? 'yes' : 'no') . "\n";
echo "  ppc:   " . ($info->isPPC() ? 'yes' : 'no') . "\n\n";

echo "--- toArray ---\n";
foreach ($info->toArray() as $k => $v) {
    printf("  %-15s %s\n", $k . ':', $v);
}

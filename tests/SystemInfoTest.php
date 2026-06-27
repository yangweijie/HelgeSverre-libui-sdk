<?php

declare(strict_types=1);

use Yangweijie\Ui2\System\SystemInfo;

/**
 * Test SystemInfo — reads real system data, no FFI needed.
 */

test('SystemInfo can be constructed', function (): void {
    $info = new SystemInfo();
    expect($info)->toBeInstanceOf(SystemInfo::class);
});

test('SystemInfo detects OS family', function (): void {
    $info = new SystemInfo();
    expect($info->os)->toBeString();
    expect($info->os)->not->toBeEmpty();
});

test('SystemInfo detects architecture', function (): void {
    $info = new SystemInfo();
    expect($info->arch)->toBeString();
    expect($info->archLabel)->toBeString();
});

test('isArm64 matches arch', function (): void {
    $info = new SystemInfo();
    $expected = str_contains($info->arch, 'arm64') || str_contains($info->arch, 'aarch64');
    expect($info->isArm64())->toBe($expected);
});

test('hostname is non-empty', function (): void {
    $info = new SystemInfo();
    expect($info->hostname)->toBeString();
    expect($info->hostname)->not->toBeEmpty();
});

test('cpuCores is positive', function (): void {
    $info = new SystemInfo();
    expect($info->cpuCores)->toBeGreaterThan(0);
});

test('memTotal is positive', function (): void {
    $info = new SystemInfo();
    expect($info->memTotal)->toBeGreaterThan(0);
});

test('diskTotal is positive', function (): void {
    $info = new SystemInfo();
    expect($info->diskTotal)->toBeGreaterThan(0);
});

test('cpuUsage returns a number or null', function (): void {
    $info = new SystemInfo();
    $usage = $info->cpuUsage();
    expect($usage === null || (is_float($usage) && $usage >= 0))->toBeTrue();
});

test('memUsed returns a number or null', function (): void {
    $info = new SystemInfo();
    $used = $info->memUsed();
    expect($used === null || (is_float($used) && $used >= 0))->toBeTrue();
});

test('diskUsed returns a number or null', function (): void {
    $info = new SystemInfo();
    $used = $info->diskUsed();
    expect($used === null || (is_float($used) && $used >= 0))->toBeTrue();
});

test('fmtBytes formats correctly', function (): void {
    expect(SystemInfo::fmtBytes(0))->toContain('0');
    expect(SystemInfo::fmtBytes(1024))->toContain('1.0');
    expect(SystemInfo::fmtBytes(1024 * 1024))->toContain('1.0');
    expect(SystemInfo::fmtBytes(1024 * 1024 * 1024))->toContain('1.0');
});

test('toArray returns all fields', function (): void {
    $info = new SystemInfo();
    $arr = $info->toArray();
    expect($arr)->toHaveKeys(['os', 'arch', 'archLabel', 'hostname', 'cpuCores', 'memTotal', 'diskTotal']);
});
<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers/Snapshot.php';

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\System\SystemInfo;

/**
 * Snapshot tests for deterministic data structures.
 *
 * First run creates baseline files in tests/__snapshots__/.
 * Subsequent runs compare against baselines.
 * To update baselines: delete .snap files and re-run.
 *
 * These tests don't need FFI — pure PHP data comparisons.
 */

test('DesignTokens default tree snapshot', function (): void {
    $t = new DesignTokens();

    // Use reflection to grab the raw token tree for snapshotting
    $refl = new ReflectionClass(DesignTokens::class);
    $prop = $refl->getProperty('tokens');
    $prop->setAccessible(true);
    $tokens = $prop->getValue($t);

    Snapshot::assert('design-tokens-default', $tokens);
});

test('DesignTokens dark tree snapshot', function (): void {
    $dark = DesignTokens::dark();

    $refl = new ReflectionClass(DesignTokens::class);
    $prop = $refl->getProperty('tokens');
    $prop->setAccessible(true);
    $tokens = $prop->getValue($dark);

    Snapshot::assert('design-tokens-dark', $tokens);
});

test('SystemInfo static properties snapshot', function (): void {
    $info = new SystemInfo();

    // Snapshot only deterministic static properties (not runtime-varying ones)
    $snapshot = [
        'os'        => $info->os,
        'arch'      => $info->arch,
        'archLabel' => $info->archLabel,
        'hostname'  => $info->hostname,
        'cpuCores'  => $info->cpuCores,
    ];

    Snapshot::assert('system-info-static', $snapshot);
});

test('SystemInfo isArm64 matches arch (cross-check)', function (): void {
    $info = new SystemInfo();
    $expected = str_contains($info->arch, 'arm64') || str_contains($info->arch, 'aarch64');
    expect($info->isArm64())->toBe($expected);
});

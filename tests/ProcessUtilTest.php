<?php

declare(strict_types=1);

use Yangweijie\Ui2\System\ProcessUtil;

/**
 * Test ProcessUtil — wraps illuminate/process.
 * These tests run real system commands, no FFI needed.
 */

test('run executes command and returns result', function (): void {
    $result = ProcessUtil::run('echo "hello"');
    expect($result->exitCode())->toBe(0);
    expect(trim($result->output()))->toBe('hello');
});

test('run with non-zero exit', function (): void {
    $result = ProcessUtil::run('exit 42');
    expect($result->exitCode())->toBe(42);
    expect($result->successful())->toBeFalse();
    expect($result->failed())->toBeTrue();
});

test('capture returns output string', function (): void {
    $output = ProcessUtil::capture('php -r "echo 42;"');
    expect($output)->toBe('42');
});

test('success returns true for successful commands', function (): void {
    expect(ProcessUtil::success('true'))->toBeTrue();
    expect(ProcessUtil::success('false'))->toBeFalse();
});

test('which returns true for existing executables', function (): void {
    expect(ProcessUtil::which('php'))->toBeTrue();
    expect(ProcessUtil::which('nonexistent_command_xyz'))->toBeFalse();
});

test('fluent API with path', function (): void {
    $result = ProcessUtil::new()
        ->path('/tmp')
        ->timeout(10)
        ->run('pwd');
    expect(trim($result->output()))->toBe('/tmp');
});

test('error output is captured', function (): void {
    $result = ProcessUtil::run('php -r "fwrite(STDERR, \"err\"); exit 1;"');
    expect($result->errorOutput())->toContain('err');
});

test('toArray returns array representation', function (): void {
    $result = ProcessUtil::run('echo "ok"');
    $arr = ProcessUtil::toArray($result);
    expect($arr)->toBeArray();
    expect($arr)->toHaveKey('exitCode');
    expect($arr)->toHaveKey('output');
    expect($arr)->toHaveKey('errorOutput');
});

test('throw throws on failure', function (): void {
    expect(fn () => ProcessUtil::run('exit 1')->throw())
        ->toThrow(RuntimeException::class);
});

test('throw does not throw on success', function (): void {
    $result = ProcessUtil::run('true')->throw();
    expect($result)->toBeInstanceOf(\Symfony\Component\Process\Process::class);
});
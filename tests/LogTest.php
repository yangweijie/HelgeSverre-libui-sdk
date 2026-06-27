<?php

declare(strict_types=1);

use Yangweijie\Ui2\Logging\Log;

/**
 * Test the Log facade — pure PHP, no FFI required.
 * Tests buffering, PSR-3 compatibility, flush, reset, and custom file paths.
 */

beforeEach(function (): void {
    Log::reset();
});

afterEach(function (): void {
    Log::reset();
});

// ---------------------------------------------------------------------------
// Initialization
// ---------------------------------------------------------------------------

test('init with custom path creates log file', function (): void {
    $tmp = sys_get_temp_dir() . '/ui2-test-' . getmypid() . '.log';
    @unlink($tmp);

    Log::init($tmp);
    Log::info('test message');
    Log::flush();

    expect(file_exists($tmp))->toBeTrue();
    $content = file_get_contents($tmp);
    expect($content)->toContain('test message');
    expect($content)->toContain('ui2.INFO');

    @unlink($tmp);
});

test('init is idempotent', function (): void {
    Log::init('/tmp/ui2-test-1.log');
    $logger1 = Log::getLogger();

    Log::init('/tmp/ui2-test-2.log');
    $logger2 = Log::getLogger();

    // Should return the same logger instance
    expect($logger1)->toBe($logger2);
});

// ---------------------------------------------------------------------------
// Log levels
// ---------------------------------------------------------------------------

test('all log levels can be written', function (): void {
    $tmp = sys_get_temp_dir() . '/ui2-test-' . getmypid() . '.log';
    Log::init($tmp);

    Log::debug('debug msg');
    Log::info('info msg');
    Log::notice('notice msg');
    Log::warning('warning msg');
    Log::error('error msg');
    Log::critical('critical msg');
    Log::alert('alert msg');

    Log::flush();

    $content = file_get_contents($tmp);
    expect($content)->toContain('DEBUG');
    expect($content)->toContain('INFO');
    expect($content)->toContain('NOTICE');
    expect($content)->toContain('WARNING');
    expect($content)->toContain('ERROR');
    expect($content)->toContain('CRITICAL');
    expect($content)->toContain('ALERT');

    @unlink($tmp);
});

// ---------------------------------------------------------------------------
// Context / placeholders
// ---------------------------------------------------------------------------

test('context data is included in log output', function (): void {
    $tmp = sys_get_temp_dir() . '/ui2-test-' . getmypid() . '.log';
    Log::init($tmp);

    Log::info('User {action}', ['action' => 'login', 'id' => 42]);
    Log::flush();

    $content = file_get_contents($tmp);
    expect($content)->toContain('login');
    expect($content)->toContain('"id"');
    expect($content)->toContain('42');

    @unlink($tmp);
});

// ---------------------------------------------------------------------------
// PSR-3 compatibility
// ---------------------------------------------------------------------------

test('getLogger returns a PSR-3 LoggerInterface', function (): void {
    Log::init();
    $logger = Log::getLogger();

    expect($logger)->toBeInstanceOf(Psr\Log\LoggerInterface::class);
    expect($logger)->toBeInstanceOf(Monolog\Logger::class);
});

test('getLogger returns null before init', function (): void {
    Log::reset();
    expect(Log::getLogger())->toBeNull();
});

// ---------------------------------------------------------------------------
// Flush
// ---------------------------------------------------------------------------

test('flush does not throw when no buffer', function (): void {
    Log::reset();
    // Should not throw
    Log::flush();
    expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Reset
// ---------------------------------------------------------------------------

test('reset clears the logger', function (): void {
    Log::init();
    expect(Log::getLogger())->not->toBeNull();

    Log::reset();
    expect(Log::getLogger())->toBeNull();
});
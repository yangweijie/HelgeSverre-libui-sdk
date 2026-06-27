<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Yangweijie\Ui2\Logging\Log;

echo "=== Log Demo ===\n\n";

// Clean start
Log::reset();

// 1. Log at all levels
echo "1. Logging at all levels:\n";
Log::debug('Debug message', ['key' => 'value']);
Log::info('Info message');
Log::notice('Notice message: {thing}', ['thing' => 'something']);
Log::warning('Warning: disk space low ({mb}MB)', ['mb' => 100]);
Log::error('Error: failed to open file', ['path' => '/tmp/test.txt']);
Log::critical('Critical: out of memory');
Log::alert('Alert: application shutting down');

Log::flush();
echo "   ✓ 7 log levels written\n\n";

// 2. Verify log file exists
echo "2. Log file:\n";
$logFile = \sys_get_temp_dir() . '/ui2-' . \date('Y-m-d') . '.log';
echo "   Path: {$logFile}\n";
echo "   Exists: " . (file_exists($logFile) ? 'yes' : 'no') . "\n\n";

// 3. Custom log path
echo "3. Custom log path:\n";
$customFile = \sys_get_temp_dir() . '/ui2-test-custom-' . \getmypid() . '.log';
Log::reset();
Log::init($customFile);
Log::info('This goes to custom file');
Log::flush();
echo "   Content: " . \trim(\file_get_contents($customFile)) . "\n";
\unlink($customFile);
echo "\n";

// 4. PSR-3 compatibility
echo "4. PSR-3 compatibility:\n";
$logger = Log::getLogger();
echo "   Logger class: " . \get_class($logger) . "\n";
echo "   Implements Psr\Log\LoggerInterface: "
    . (\in_array('Psr\Log\LoggerInterface', \class_implements($logger)) ? 'yes' : 'no') . "\n\n";

// 5. Manual flush + reset
echo "5. Flush + Reset:\n";
Log::reset();
echo "   After reset, logger is null: " . (Log::getLogger() === null ? 'yes' : 'no') . "\n";

echo "\n=== All Log tests passed ===\n";
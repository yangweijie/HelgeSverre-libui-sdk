<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Yangweijie\Ui2\System\ProcessUtil;

echo "=== ProcessUtil Demo ===\n\n";

// 1. Simple one-shot command
echo "1. Simple run:\n";
$r = ProcessUtil::run('echo "Hello from PHP"');
echo "  exit: {$r->exitCode()}, output: " . trim($r->output()) . "\n\n";

// 2. Capture (get output string directly)
echo "2. Capture (throws on failure):\n";
$out = ProcessUtil::capture('php -r "echo 42;"');
echo "  output: {$out}\n\n";

// 3. Fluent API with path + timeout
echo "3. Fluent:\n";
$r = ProcessUtil::new()
    ->path('/tmp')
    ->timeout(10)
    ->run('pwd');
echo "  pwd: " . trim($r->output()) . "\n\n";

// 4. Success check
echo "4. Success check:\n";
echo "  ls exists:  " . (ProcessUtil::success('ls /tmp') ? 'yes' : 'no') . "\n";
echo "  which php:  " . (ProcessUtil::which('php') ? 'yes' : 'no') . "\n";
echo "  which nvim: " . (ProcessUtil::which('nvim') ? 'yes' : 'no') . "\n\n";

// 5. Failed command
echo "5. Failed command:\n";
$r = ProcessUtil::run('ls /nonexistent_path_xyz 2>&1; exit 1');
echo "  exit: {$r->exitCode()}, success: " . ($r->successful() ? 'yes' : 'no') . "\n";
echo "  output: " . trim($r->output()) . "\n\n";

// 6. Error output
echo "6. Error output:\n";
$r = ProcessUtil::run('php -r "fwrite(STDERR, \"error msg\"); exit 1;"');
echo "  stderr: " . trim($r->errorOutput()) . "\n";
echo "  failed: " . ($r->failed() ? 'yes' : 'no') . "\n\n";

// 7. toArray
echo "7. toArray:\n";
$r = ProcessUtil::run('echo "hello"');
print_r(ProcessUtil::toArray($r));
echo "\n";

// 8. throw on failure
echo "8. throw on failure (expected to catch):\n";
try {
    ProcessUtil::run('exit 42')->throw();
} catch (\Throwable $e) {
    echo "  Caught: " . $e->getMessage() . "\n";
}

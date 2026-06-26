<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

use Illuminate\Process\Factory;
use Illuminate\Process\PendingProcess;
use Illuminate\Process\ProcessResult;

/**
 * Process execution utility — wraps illuminate/process with convenience methods.
 *
 * Provides a fluent API for running shell commands, capturing output,
 * and handling timeouts.  Built on top of Symfony Process.
 *
 * ```php
 * $result = ProcessUtil::run('ls -la');
 * echo $result->output();       // stdout
 * echo $result->exitCode();     // 0
 *
 * ProcessUtil::new()
 *     ->path('/tmp')
 *     ->timeout(30)
 *     ->run('php -v')
 *     ->throw();
 * ```
 */
class ProcessUtil
{
    private PendingProcess $pending;

    public function __construct()
    {
        $this->pending = (new Factory())->newPendingProcess();
    }

    /**
     * Create a new ProcessUtil instance (fluent constructor).
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Run a command and return the result.
     *
     * Shortcut for one-off commands.  For full config use new()->...->run().
     */
    public static function run(string|array $command, ?callable $output = null): ProcessResult
    {
        return (new self())->pending->run($command, $output);
    }

    /**
     * Set the command to run.
     */
    public function command(string|array $command): static
    {
        $this->pending->command($command);
        return $this;
    }

    /**
     * Set the working directory.
     */
    public function path(string $path): static
    {
        $this->pending->path($path);
        return $this;
    }

    /**
     * Set the timeout in seconds (default 60).
     */
    public function timeout(int $seconds): static
    {
        $this->pending->timeout($seconds);
        return $this;
    }

    /**
     * Disable timeout (may run forever).
     */
    public function forever(): static
    {
        $this->pending->forever();
        return $this;
    }

    /**
     * Set environment variables.
     */
    public function env(array $env): static
    {
        $this->pending->env($env);
        return $this;
    }

    /**
     * Set stdin input.
     */
    public function input(string|int|float|bool $input): static
    {
        $this->pending->input($input);
        return $this;
    }

    /**
     * Disable output capture (saves memory for long-running processes).
     */
    public function quietly(): static
    {
        $this->pending->quietly();
        return $this;
    }

    /**
     * Execute the process synchronously (instance method).
     *
     * @param  array|string|null  $command  Override command if needed.
     * @param  callable|null  $output  Callback for real-time output (string $type, string $buffer).
     * @return ProcessResult
     */
    public function execute(string|array|null $command = null, ?callable $output = null): ProcessResult
    {
        return $this->pending->run($command, $output);
    }

    /**
     * Run a command and return stdout as a trimmed string.
     * Throws if the process fails.
     */
    public static function capture(string|array $command): string
    {
        return self::run($command)->throw()->output();
    }

    /**
     * Run a command and return true if exit code is 0.
     */
    public static function success(string|array $command): bool
    {
        return self::run($command)->successful();
    }

    /**
     * Run a command in the background.
     */
    public function start(string|array|null $command = null, ?callable $output = null): void
    {
        $this->pending->start($command, $output);
    }

    /**
     * Check if a program is available in PATH.
     */
    public static function which(string $binary): bool
    {
        return self::run(\str_starts_with(\PHP_OS_FAMILY, 'WIN')
            ? "where {$binary} 2>nul"
            : "command -v {$binary} 2>/dev/null"
        )->successful();
    }

    /**
     * Get the result as a readable array.
     */
    public static function toArray(ProcessResult $result): array
    {
        return [
            'exit_code'   => $result->exitCode(),
            'successful'  => $result->successful(),
            'output'      => $result->output(),
            'error_output' => $result->errorOutput(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

/**
 * Registry of all available System capabilities.
 *
 * Capabilities are auto-registered on first access.  Use {@see has()} and
 * {@see require()} before constructing System classes to guarantee they
 * will work at runtime.
 *
 * ```php
 * if (!CapabilityRegistry::has('audio')) {
 *     fwrite(STDERR, "Audio disabled: " . CapabilityRegistry::reason('audio'));
 *     exit(1);
 * }
 * ```
 */
final class CapabilityRegistry
{
    /** @var array<string, Capability> */
    private static array $registry = [];

    /**
     * Register a capability (replaces any existing entry with the same name).
     */
    public static function register(Capability $capability): void
    {
        self::$registry[$capability->name()] = $capability;
    }

    /**
     * Check whether a named capability is available.
     *
     * Lazily registers built-in capabilities by name if not already registered.
     */
    public static function has(string $name): bool
    {
        $cap = self::resolve($name);
        return $cap !== null && $cap->available();
    }

    /**
     * Return the reason a capability is unavailable, or null if available.
     */
    public static function reason(string $name): ?string
    {
        $cap = self::resolve($name);
        return $cap?->reason();
    }

    /**
     * Require a capability — throws a clear exception if unavailable.
     *
     * @return Capability The capability for further inspection.
     * @throws CapabilityException when the capability is unavailable.
     */
    public static function require(string $name): Capability
    {
        $cap = self::resolve($name);
        if ($cap === null) {
            throw new CapabilityException(
                "Unknown capability: {$name}",
            );
        }
        if (!$cap->available()) {
            $deps = $cap->dependencies();
            $msg = "Capability '{$name}' is not available on this system.";
            if ($cap->reason() !== null) {
                $msg .= "\n  Reason: {$cap->reason()}";
            }
            if ($deps !== []) {
                $msg .= "\n  Missing dependencies:\n    - " . implode("\n    - ", $deps);
            }
            throw new CapabilityException($msg);
        }
        return $cap;
    }

    /**
     * List all registered capability names.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::$registry);
    }

    /**
     * Resolve a capability by name — try auto-registration for built-ins.
     */
    private static function resolve(string $name): ?Capability
    {
        if (isset(self::$registry[$name])) {
            return self::$registry[$name];
        }
        return self::autoRegister($name);
    }

    /**
     * Auto-register a built-in capability if the name matches a known class.
     */
    private static function autoRegister(string $name): ?Capability
    {
        $map = [
            'audio'      => AudioCapability::class,
            'tray'       => TrayCapability::class,
            'hotkey'     => HotkeyCapability::class,
            'systeminfo' => SystemInfoCapability::class,
            'process'    => ProcessCapability::class,
            'automation' => AutomationCapability::class,
        ];

        $class = $map[$name] ?? null;
        if ($class === null || !class_exists($class)) {
            return null;
        }

        $cap = new $class();
        self::$registry[$name] = $cap;
        return $cap;
    }
}

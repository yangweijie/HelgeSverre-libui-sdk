<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\State;

/**
 * Effect descriptor — a pure description of a side-effect to execute after update.
 *
 * Effects decouple side-effects (API calls, file I/O, timers, clipboard) from the
 * Update function, keeping it pure and testable. An Update function returns
 * an {@see UpdateResult} containing the new Model plus an array of Effects.
 *
 * Concrete effects extend this class:
 *
 *   class HttpGetEffect extends Effect {
 *       public function __construct(
 *           public readonly string $url,
 *           public readonly callable $onResponse,
 *       ) {}
 *   }
 *
 * The AppRuntime's effect runner reads each Effect and executes it.
 * For the initial MVP the effect list is stored but not automatically run.
 */
abstract class Effect {}

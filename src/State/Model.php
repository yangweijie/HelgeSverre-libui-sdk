<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\State;

/**
 * Model marker interface — marks a readonly class as the source-of-truth model.
 *
 * The Model is an immutable value object that holds all application state
 * consumed by the View function. It MUST be a readonly class (PHP 8.1+).
 *
 * @see AppRuntime  Owns a Model and dispatches Messages to produce the next Model.
 */
interface Model {}

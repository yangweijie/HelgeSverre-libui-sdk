<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\State;

use Yangweijie\Ui2\Events\Event;

/**
 * Emitted by {@see AppRuntime::dispatch()} after every state transition, so the
 * automation / observability layer can observe state changes without touching
 * business code (see docs/zh/design/observability-automation.md §4.5).
 */
final class StateChangedEvent implements Event
{
    /**
     * @param Msg   $msg       The message that triggered the transition.
     * @param Model $oldModel  Model before the update ran.
     * @param Model $newModel  Model after the update ran.
     */
    public function __construct(
        public readonly Msg $msg,
        public readonly Model $oldModel,
        public readonly Model $newModel,
    ) {
    }

    public function name(): string
    {
        return 'state.changed';
    }
}

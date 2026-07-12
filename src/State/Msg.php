<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\State;

/**
 * Msg marker interface — marks a PHP enum value as a Message in Elm-architecture.
 *
 * Implement as a {@see \UnitEnum} (backed or pure). Each enum case represents
 * one kind of user interaction (e.g. `CounterMsg::Inc`, `FormMsg::Submit`).
 *
 * @see AppRuntime::dispatch()  Accepts any Msg and runs the Update function.
 */
interface Msg {}

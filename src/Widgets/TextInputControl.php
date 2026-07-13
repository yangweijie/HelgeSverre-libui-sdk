<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

/**
 * Common contract for editable text inputs that own their value/caret state and
 * can be driven by the Surface's IME (NSTextView) overlay.
 *
 * Implemented by {@see TextAreaControl} and {@see SearchFieldControl} so the
 * Surface's IME notify callback can commit composed text uniformly.
 */
interface TextInputControl
{
    public function getValue(): string;

    public function setValue(string $value): static;

    public function getCursor(): int;

    public function setCursor(int $cursor): static;
}

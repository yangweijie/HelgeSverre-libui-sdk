<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

/**
 * A self-drawn popover (.popover) — a small centred {@see DialogControl} card
 * used for contextual actions / confirmations. Inherits overlay / scrim / focus
 * / Esc-dismiss / button behaviour from DialogControl.
 *
 * ```php
 * $pop = new PopoverControl('ctx', '操作', '确认执行此操作？');
 * $pop->bind($surface)->onClose(fn ($id) => $id === 'ok' && doIt());
 * $pop->open();
 * ```
 */
final class PopoverControl extends DialogControl
{
    /**
     * @param list<array{id:string,label:string,variant?:string}> $buttons
     */
    public function __construct(
        string $name,
        string $title,
        string $message,
        array $buttons = [['id' => 'ok', 'label' => '确定', 'variant' => 'filled']],
        float $width = 260.0,
        float $height = 160.0,
    ) {
        parent::__construct($name, $title, $message, $buttons, $width, $height, 'popover');
    }
}

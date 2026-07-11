<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

/**
 * A self-drawn bottom sheet (.sheet) — a {@see DialogControl} anchored to the
 * bottom edge, stretching to fill the width. Inherits overlay / scrim / focus /
 * Esc-dismiss / button behaviour from DialogControl.
 *
 * ```php
 * $sheet = new SheetControl('filters', '筛选', '选择筛选条件…');
 * $sheet->bind($surface)->onClose(fn () => applyFilters());
 * $sheet->open();
 * ```
 */
final class SheetControl extends DialogControl
{
    /**
     * @param list<array{id:string,label:string,variant?:string}> $buttons
     */
    public function __construct(
        string $name,
        string $title,
        string $message,
        array $buttons = [['id' => 'ok', 'label' => '应用', 'variant' => 'filled']],
        float $height = 220.0,
    ) {
        parent::__construct($name, $title, $message, $buttons, 0.0, $height, 'bottom');
    }
}

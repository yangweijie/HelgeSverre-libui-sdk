<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

/**
 * A self-drawn side drawer (.drawer) — a {@see DialogControl} anchored to a
 * screen edge (right by default, left if $side = 'left'). The card stretches to
 * fill the edge-to-edge height; everything else (overlay, scrim, focus capture,
 * Esc-to-dismiss, button callbacks) is inherited from DialogControl.
 *
 * ```php
 * $drawer = new DrawerControl('menu', '导航', '左侧导航内容…', side: 'left');
 * $drawer->bind($surface)->onClose(fn ($id) => $id === 'ok' && apply());
 * $drawer->open();
 * ```
 */
final class DrawerControl extends DialogControl
{
    /**
     * @param list<array{id:string,label:string,variant?:string}> $buttons
     */
    public function __construct(
        string $name,
        string $title,
        string $message,
        array $buttons = [['id' => 'ok', 'label' => '确定', 'variant' => 'filled']],
        float $width = 320.0,
        string $side = 'right',
    ) {
        parent::__construct($name, $title, $message, $buttons, $width, 0.0, $side === 'left' ? 'left' : 'right');
    }
}

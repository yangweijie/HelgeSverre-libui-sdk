<?php

declare(strict_types=1);

use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Widgets\DrawerControl;
use Yangweijie\Ui2\Widgets\PopoverControl;
use Yangweijie\Ui2\Widgets\SheetControl;

test('DrawerControl (right) anchors a full-height card to the end of a row overlay', function (): void {
    $d = new DrawerControl('nav', '导航', '内容', side: 'right');
    $overlay = $d->overlay();
    expect($overlay->style->direction)->toBe(LayoutStyle::ROW);
    expect($overlay->style->justify)->toBe(LayoutStyle::JUSTIFY_END);
    expect($overlay->style->align)->toBe(LayoutStyle::ALIGN_STRETCH);

    $card = $overlay->children[0];
    expect($card->style->width)->toBe(320.0);
    expect($card->style->height)->toBeNull();
});

test('DrawerControl (left) anchors to the start of a row overlay', function (): void {
    $d = new DrawerControl('nav', '导航', '内容', side: 'left');
    $overlay = $d->overlay();
    expect($overlay->style->direction)->toBe(LayoutStyle::ROW);
    expect($overlay->style->justify)->toBe(LayoutStyle::JUSTIFY_START);
});

test('SheetControl (bottom) anchors a full-width card to the end of a column overlay', function (): void {
    $s = new SheetControl('filters', '筛选', '内容');
    $overlay = $s->overlay();
    expect($overlay->style->direction)->toBe(LayoutStyle::COLUMN);
    expect($overlay->style->justify)->toBe(LayoutStyle::JUSTIFY_END);
    expect($overlay->style->align)->toBe(LayoutStyle::ALIGN_STRETCH);

    $card = $overlay->children[0];
    expect($card->style->height)->toBe(220.0);
    expect($card->style->width)->toBeNull();
});

test('PopoverControl is a small centred card', function (): void {
    $p = new PopoverControl('ctx', '操作', '确认？');
    $overlay = $p->overlay();
    expect($overlay->style->justify)->toBe(LayoutStyle::JUSTIFY_CENTER);
    expect($overlay->style->align)->toBe(LayoutStyle::ALIGN_CENTER);

    $card = $overlay->children[0];
    expect($card->style->width)->toBe(260.0);
    expect($card->style->height)->toBe(160.0);
});

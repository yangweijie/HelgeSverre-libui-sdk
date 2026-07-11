<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\DialogBodySpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\DialogCardSpec;
use Yangweijie\Ui2\Semantics\WidgetRole;

/**
 * A self-drawn modal dialog, wired into a {@see Surface} as an overlay.
 *
 * Builds an overlay subtree: a dim scrim (drawn by the Surface) + a centred card
 * ({@see DialogCardSpec}) holding a title/message block ({@see DialogBodySpec})
 * and a row of real {@see ButtonSpec} action buttons. Because the buttons are
 * ordinary self-drawn leaves, they get the full hover / focus / click treatment
 * for free — the control just shows/hides the overlay and reports which button
 * was pressed.
 *
 * ```php
 * $dialog = new DialogControl('confirm', 'Delete?', 'This cannot be undone.', [
 *     ['id' => 'cancel', 'label' => 'Cancel', 'variant' => 'outline'],
 *     ['id' => 'ok',     'label' => 'Delete', 'variant' => 'filled'],
 * ]);
 * $dialog->bind($surface)->onClose(fn ($id) => $id === 'ok' && delete());
 * $dialog->open();   // or open from a button's click handler
 * ```
 */
class DialogControl
{
    private LayoutNode $overlay;

    /** @var list<string> */
    private array $buttonIds;

    private ?Surface $surface = null;

    private bool $visible = false;

    /** @var callable(string):void|null */
    private $onClose = null;

    /**
     * @param list<array{id:string,label:string,variant?:string}> $buttons
     * @param string $position One of center | right | left | top | bottom | popover.
     *                          right/left = Drawer, top/bottom = Sheet, popover = small card.
     */
    public function __construct(
        private readonly string $name,
        string $title,
        string $message,
        array $buttons = [['id' => 'ok', 'label' => '确定', 'variant' => 'filled']],
        float $width = 360.0,
        float $height = 216.0,
        string $position = 'center',
    ) {
        $this->buttonIds = array_map(static fn ($b) => $b['id'], $buttons);

        $card = LayoutNode::column(gap: 8, padding: 16);
        $card->spec = new DialogCardSpec(radius: 14.0);

        // Size the card per position: drawers fill the edge (cross-axis stretch),
        // sheets fill the width, popover/centre are fixed boxes.
        $isDrawer = $position === 'right' || $position === 'left';
        $isSheet = $position === 'top' || $position === 'bottom';
        if ($isDrawer) {
            $card->style->width = $width <= 0 ? 320.0 : $width;
        } elseif ($isSheet) {
            $card->style->height = $height <= 0 ? 220.0 : $height;
        } else {
            $card->style->width = $width;
            $card->style->height = $height;
        }

        $bodyW = ($card->style->width ?? 260.0) - 32;
        $bodyH = ($card->style->height ?? 160.0) - 32 - 44;
        $card->child(LayoutNode::leaf(
            null,
            new DialogBodySpec(title: $title, message: $message),
            width: $bodyW,
            height: $bodyH,
        ));

        $btnRow = LayoutNode::row(gap: 8, justify: LayoutStyle::JUSTIFY_END, padding: 0, id: null);
        $btnRow->style->height = 44.0;
        foreach ($buttons as $b) {
            $btnRow->child(LayoutNode::leaf(
                "{$this->name}:{$b['id']}",
                new ButtonSpec($b['label'], $b['variant'] ?? 'filled'),
                width: 96,
                height: 34,
            ));
        }
        $card->child($btnRow);

        // Anchor the card via the overlay container's justify/align so it sits
        // at the requested edge without needing the area size up front.
        $overlay = match ($position) {
            'right' => LayoutNode::row(justify: LayoutStyle::JUSTIFY_END, align: LayoutStyle::ALIGN_STRETCH),
            'left' => LayoutNode::row(justify: LayoutStyle::JUSTIFY_START, align: LayoutStyle::ALIGN_STRETCH),
            'top' => LayoutNode::column(justify: LayoutStyle::JUSTIFY_START, align: LayoutStyle::ALIGN_STRETCH),
            'bottom' => LayoutNode::column(justify: LayoutStyle::JUSTIFY_END, align: LayoutStyle::ALIGN_STRETCH),
            default => LayoutNode::column(justify: LayoutStyle::JUSTIFY_CENTER, align: LayoutStyle::ALIGN_CENTER),
        };
        $overlay->withRole(WidgetRole::Dialog);
        $overlay->child($card);
        $this->overlay = $overlay;
    }

    /** The overlay node tree — shown via {@see Surface::setOverlay()}. */
    public function overlay(): LayoutNode
    {
        return $this->overlay;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    /** Register button + escape-dismiss handlers on a Surface and keep it. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;

        foreach ($this->buttonIds as $id) {
            $surface->onClick("{$this->name}:{$id}", fn () => $this->close($id));
        }

        $surface->onOverlayDismiss(fn () => $this->close(''));

        return $this;
    }

    /** Show the dialog (installs the overlay on the Surface). */
    public function open(): void
    {
        $this->visible = true;
        $this->surface?->setOverlay($this->overlay);
    }

    /** Hide the dialog and report which button (or '' for Escape/scrim) was used. */
    public function close(string $buttonId = ''): void
    {
        if (! $this->visible) {
            return;
        }

        $this->visible = false;
        $this->surface?->setOverlay(null);

        if ($this->onClose !== null) {
            ($this->onClose)($buttonId);
        }
    }

    /** @param callable(string):void $fn */
    public function onClose(callable $fn): static
    {
        $this->onClose = $fn;

        return $this;
    }
}

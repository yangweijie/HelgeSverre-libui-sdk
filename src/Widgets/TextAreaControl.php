<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextAreaRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextAreaSpec;

/**
 * A self-drawn multiline text editor (.textarea).
 *
 * Owns the editable state (value + caret + scroll offset) and wires it into a
 * {@see Surface}: typing routes through Surface::onText (printable chars,
 * Backspace, Enter-as-newline) and arrow keys through Surface::onCaret. After
 * every edit the caret is scrolled into view and a redraw is queued.
 *
 * ```php
 * $ta = new TextAreaControl('notes', '写点什么…', width: 280, height: 140);
 * $ta->bind($surface)->onChange(fn ($v) => save($v));
 * ```
 */
class TextAreaControl
{
    private const PAD = 8.0;

    private LayoutNode $leaf;

    private ?Surface $surface = null;

    private string $value = '';

    private int $cursor = 0;

    private float $scrollY = 0.0;

    // ── scrollbar drag state ─────────────────────────────────────────────

    /** Drag anchor (ly at press), null = no drag, -1.0 = rejected (not on sb). */
    private ?float $sbAnchor = null;

    /** scrollY captured at the start of the drag. */
    private float $sbBaseScroll = 0.0;

    /** Total content height needed for scroll-delta computation. */
    private float $sbTotalH = 0.0;

    /** Visible height (h - 2*PAD) needed for the scroll-delta formula. */
    private float $sbVisH = 0.0;

    /** Max scroll range, cached during drag. */
    private float $sbMaxScroll = 0.0;

    /** @var callable(string):void|null */
    private $onChange = null;

    /** Unique instance ID for debugging. */
    private static int $nextId = 0;
    private int $instanceId;

    public function __construct(
        private readonly string $name,
        private readonly string $placeholder = '',
        private readonly float $width = 240.0,
        private readonly float $height = 120.0,
        private readonly float $radius = 6.0,
        private readonly float $fontSize = 14.0,
        private readonly float $lineHeight = 20.0,
    ) {
        $this->instanceId = ++self::$nextId;
        $this->leaf = LayoutNode::leaf(
            "textarea:{$name}",
            new TextAreaSpec(
                placeholder: $placeholder,
                radius: $radius,
                fontSize: $fontSize,
                lineHeight: $lineHeight,
                control: $this,
            ),
            width: $width,
            height: $height,
        );
    }

    /** The leaf node to drop into a parent layout. */
    public function root(): LayoutNode
    {
        return $this->leaf;
    }

    /** Register text-input + caret handlers on a Surface and keep it. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;

        $surface->onText("textarea:{$this->name}", function (string $char, bool $backspace): void {
            if ($backspace) {
                $this->backspace();
            } else {
                $this->insertChar($char);
            }
        });

        $surface->onCaret("textarea:{$this->name}", fn (string $dir) => $this->moveCaret($dir));

        // Scrollbar thumb drag: click-to-position + drag-to-scroll.
        $surface->onDrag("textarea:{$this->name}", function (float $lx, float $ly, float $w, float $h): void {
            $inSb = $lx >= $w - 15 && $lx <= $w - 8;

            if ($this->sbAnchor === null) {
                // ── First call (drag start / scrollbar click) ──────────
                if (!$inSb) {
                    $this->sbAnchor = -1.0; // sentinel — not a sb drag
                    return;
                }
                $maxW = max(1.0, $w - 16);
                $r    = new TextAreaRenderer();
                [$lines,] = $r->wrap($this->value, $maxW, $this->fontSize);
                $this->sbTotalH   = max(1.0, count($lines) * $this->lineHeight);
                $this->sbVisH     = max(1.0, $h - 16);
                $this->sbMaxScroll = max(0.0, $this->sbTotalH - $this->sbVisH);

                // Click position → scroll fraction (track-relative).
                $sbH    = $h - 16;
                $ratio  = $this->sbVisH / $this->sbTotalH;
                $thumbH = max(24.0, $sbH * $ratio);
                $trackH = max(1.0, $sbH - $thumbH);
                $frac   = max(0.0, min(1.0, ($ly - 8) / $trackH));

                $this->sbAnchor    = $ly;
                $this->sbBaseScroll = $frac * $this->sbMaxScroll;
                $this->scrollY     = max(0.0, min($this->sbMaxScroll, $this->sbBaseScroll));
                $this->afterEdit();
                return;
            }

            // ── Rejected drag (press was on text body) — no-op ─────────
            if ($this->sbAnchor < 0) {
                return;
            }

            // ── Subsequent frames — drag delta → scroll delta ──────────
            // d_scrollY/d_ly = totalH / sbH  (derived from the linear
            // thumb-position / scrollY relationship, see carea docs).
            $dy = ($ly - $this->sbAnchor);
            $this->scrollY = max(0.0, min(
                $this->sbMaxScroll,
                $this->sbBaseScroll + $dy * $this->sbTotalH / max(1.0, $h - 16)
            ));
            $this->afterEdit();
        });

        $surface->onDragEnd("textarea:{$this->name}", function (): void {
            $this->sbAnchor = null;
        });

        return $this;
    }

    /** Insert a single character at the caret (Enter becomes a newline). */
    public function insertChar(string $char): void
    {
        $insert = ($char === "\r" || $char === "\n") ? "\n" : $char;
        $this->value = mb_substr($this->value, 0, $this->cursor)
            . $insert
            . mb_substr($this->value, $this->cursor);
        $this->cursor++;
        $this->afterEdit();
    }

    /** Delete the character before the caret. */
    public function backspace(): void
    {
        if ($this->cursor > 0) {
            $this->value = mb_substr($this->value, 0, $this->cursor - 1)
                . mb_substr($this->value, $this->cursor);
            $this->cursor--;
            $this->afterEdit();
        }
    }

    public function setValue(string $value): static
    {
        $oldValue = $this->value;
        $this->value = $value;
        $this->cursor = mb_strlen($value);
        fwrite(STDERR, "[TextAreaControl##{$this->instanceId}] setValue: \"{$oldValue}\" (len=" . mb_strlen($oldValue) . ") -> \"{$value}\" (len=" . mb_strlen($value) . ")\n");
        $this->afterEdit();

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getCursor(): int
    {
        return $this->cursor;
    }

    public function setCursor(int $cursor): static
    {
        $this->cursor = max(0, min($cursor, mb_strlen($this->value)));
        $this->afterEdit();
        return $this;
    }

    /** Set value and cursor in one call to avoid double afterEdit/redraw. */
    public function setValueWithCursor(string $value, int $cursor): static
    {
        $this->value = $value;
        $this->cursor = max(0, min($cursor, mb_strlen($value)));
        $this->afterEdit();
        return $this;
    }

    /** @param callable(string):void $fn */
    public function onChange(callable $fn): static
    {
        $this->onChange = $fn;

        return $this;
    }

    private function afterEdit(): void
    {
        fwrite(STDERR, "[TextAreaControl##{$this->instanceId}] afterEdit: value=\"" . $this->value . "\" cursor=" . $this->cursor . "\n");
        $this->syncSpec();
        // Layout-dependent work (caret auto-scroll, repaint) only makes sense
        // once the control is bound to a live Surface — and would otherwise pull
        // in TextLayout during headless unit tests.
        if ($this->surface !== null) {
            $this->autoscroll();
            $this->surface->redraw();
        }
        if ($this->onChange !== null) {
            ($this->onChange)($this->value);
        }
    }

    private function syncSpec(): void
    {
        fwrite(STDERR, "[TextAreaControl##{$this->instanceId}] syncSpec: setting leaf->spec value=\"" . $this->value . "\" (len=" . mb_strlen($this->value) . ")\n");
        $this->leaf->spec = new TextAreaSpec(
            value: $this->value,
            placeholder: $this->placeholder,
            radius: $this->radius,
            fontSize: $this->fontSize,
            lineHeight: $this->lineHeight,
            scrollY: $this->scrollY,
            cursor: $this->cursor,
            control: $this,
        );
    }

    public function moveCaret(string $dir): void
    {
        $oldCursor = $this->cursor;
        fwrite(STDERR, "[TextAreaControl##{$this->instanceId}] moveCaret: dir={$dir} oldCursor={$oldCursor} valueLen=" . mb_strlen($this->value) . "\n");
        // Without a bound Surface there is no layout to compute visual lines,
        // so fall back to simple character-level movement (enough for headless tests).
        if ($this->surface === null) {
            if ($dir === 'left') {
                $this->cursor = max(0, $this->cursor - 1);
            } elseif ($dir === 'right') {
                $this->cursor = min(mb_strlen($this->value), $this->cursor + 1);
            }
            fwrite(STDERR, "[TextAreaControl##{$this->instanceId}] moveCaret: newCursor={$this->cursor}\n");
            $this->afterEdit();

            return;
        }

        $maxW = max(1.0, $this->width - 2 * self::PAD);
        $renderer = new TextAreaRenderer();
        [$lines, $starts] = $renderer->wrap($this->value, $maxW, $this->fontSize);
        [$line, $x] = $renderer->caretPosition($lines, $starts, $this->cursor, $maxW, $this->fontSize);

        if ($dir === 'left') {
            $this->cursor = max(0, $this->cursor - 1);
        } elseif ($dir === 'right') {
            $this->cursor = min(mb_strlen($this->value), $this->cursor + 1);
        } elseif ($dir === 'up' && $line > 0) {
            $this->cursor = $starts[$line - 1]
                + $renderer->cursorOffsetForLine($x, $line - 1, $lines, $maxW, $this->fontSize);
        } elseif ($dir === 'down' && $line < count($lines) - 1) {
            $this->cursor = $starts[$line + 1]
                + $renderer->cursorOffsetForLine($x, $line + 1, $lines, $maxW, $this->fontSize);
        }

        $this->afterEdit();
    }

    /** Keep the caret's line within the visible viewport after an edit. */
    private function autoscroll(): void
    {
        $maxW = max(1.0, $this->width - 2 * self::PAD);
        $renderer = new TextAreaRenderer();
        [$lines, $starts] = $renderer->wrap($this->value, $maxW, $this->fontSize);
        [$line, ] = $renderer->caretPosition($lines, $starts, $this->cursor, $maxW, $this->fontSize);

        $innerH = $this->height - 2 * self::PAD;
        $caretTop = $line * $this->lineHeight;
        $caretBottom = $caretTop + $this->lineHeight;

        if ($caretTop < $this->scrollY) {
            $this->scrollY = $caretTop;
        } elseif ($caretBottom > $this->scrollY + $innerH) {
            $this->scrollY = $caretBottom - $innerH;
        }
        $this->scrollY = max(0.0, $this->scrollY);
    }
}

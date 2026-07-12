<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Libui\Draw\Path;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Libui\Draw\StrokeParams;
use Libui\Generated\Enum\DrawTextAlign;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeLine;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;
use Yangweijie\Ui2\Rendering\SaveClip;

/**
 * Self-drawn multiline text field (.textarea).
 *
 * Mirrors the single-line {@see TextFieldRenderer} chrome (surface fill, primary
 * border when focused, token-driven wash) but paints wrapped lines inside a
 * clipped region and a blinking caret at the insertion point. Word wrapping is
 * measured with a live {@see TextLayout} (so proportional fonts wrap correctly);
 * the wrapped block is wrapped in a {@see SaveClip} so it never bleeds past the
 * rounded frame or over the border — and scrolls with the field's scrollY.
 */
final class TextAreaRenderer implements WidgetRenderer
{
    use TokenWash;

    private const PAD = 8.0;

    /** @var array<string,float> Per-(text,size) measured widths, reused across frames. */
    private static array $measureCache = [];

    private ?DesignTokens $tokens = null;

    public static function type(): string
    {
        return 'text_area';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof TextAreaSpec) {
            throw new \InvalidArgumentException('TextAreaRenderer requires a TextAreaSpec');
        }

        $bg = $tokens->color('color.surface');
        $border = $spec->focused
            ? $this->paint($spec, $tokens, 'color.primary')
            : $tokens->color('color.track');

        $commands = [
            new FillRoundedRect(0, 0, $width, $height, $spec->radius, $bg),
            new StrokeRoundedRect(0.75, 0.75, $width - 1.5, $height - 1.5, $spec->radius, $border, StrokeParams::solid(1.5)),
        ];

        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        $this->tokens = $tokens;

        if (! $spec instanceof TextAreaSpec) {
            throw new \InvalidArgumentException('TextAreaRenderer requires a TextAreaSpec');
        }

        fwrite(STDERR, "[TextAreaRenderer] render: value=\"" . $spec->value . "\" width=" . $width . " height=" . $height . "\n");

        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $lineH = $spec->lineHeight > 0 ? $spec->lineHeight : $spec->fontSize * 1.4;
        $maxW = max(1.0, $width - 2 * self::PAD);

        $onSurface = $tokens->color('color.onSurface');
        $color = $spec->enabled ? $onSurface : Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.4);

        fwrite(STDERR, "[TextAreaRenderer] color: enabled=" . ($spec->enabled ? 'true' : 'false') . " focused=" . ($spec->focused ? 'true' : 'false') . " onSurface=" . ($onSurface ? 'found' : 'null') . "\n");

        $text = $spec->value;
        $isPlaceholder = $text === '';
        $shown = $isPlaceholder ? $spec->placeholder : $text;

        fwrite(STDERR, "[TextAreaRenderer] text=\"" . $text . "\" shown=\"" . $shown . "\" isPlaceholder=" . ($isPlaceholder ? 'true' : 'false') . "\n");

        if ($shown === '') {
            return new RenderCommandList($commands);
        }

        [$lines, $starts] = $this->wrap($shown, $maxW, $spec->fontSize);
        fwrite(STDERR, "[TextAreaRenderer] wrapped " . count($lines) . " lines\n");

        $font = $tokens->font($spec->fontSize);
        $scrollY = $spec->scrollY;
        $innerH = $height - 2 * self::PAD;

        $firstVisible = max(0, (int) floor($scrollY / $lineH));
        $lastVisible = min(count($lines) - 1, (int) ceil(($scrollY + $innerH) / $lineH));

        $children = [];
        for ($i = $firstVisible; $i <= $lastVisible; $i++) {
            $lineText = $lines[$i];
            if ($lineText === '') {
                continue;
            }
            $y = self::PAD + $i * $lineH - $scrollY;
            $str = new AttributedString();
            $str->append($lineText, Attribute::fromColor($color), Attribute::size($spec->fontSize));
            $layout = new TextLayout($str, $font, $maxW, DrawTextAlign::Left);
            [, $th] = $layout->extents();
            $children[] = new DrawText($layout, self::PAD, $y + ($lineH - $th) / 2);
        }

        // Caret: only when focused and there is real text (or an empty field shows it at the start).
        if ($spec->focused) {
            [$caretLine, $caretX] = $this->caretPosition($lines, $starts, $spec->cursor, $maxW, $spec->fontSize);
            if ($caretLine >= $firstVisible && $caretLine <= $lastVisible) {
                $cx = self::PAD + $caretX;
                $cyTop = self::PAD + $caretLine * $lineH - $scrollY + ($lineH - $spec->fontSize) / 2;
                $children[] = new StrokeLine($cx, $cyTop, $cx, $cyTop + $spec->fontSize, $color, 1.5);
            }
        }

        // Clip the wrapped text + caret to the inner frame so scrolling never
        // bleeds over the border or past the rounded corners.
        $clip = (new Path())->addRectangle(1.0, 1.0, $width - 2.0, $height - 2.0)->end();
        $commands[] = new SaveClip($clip, $children);

        // Overlay scrollbar — visible only when content overflows the viewport.
        $totalH = count($lines) * $lineH;
        $visH = $height - 2 * self::PAD;
        if ($totalH > $visH + 0.5) {
            $sbW = 7.0;
            $sbX = $width - self::PAD - $sbW;
            $sbH = $height - 2 * self::PAD;
            $ratio = $visH / $totalH;
            $thumbH = max(24.0, $sbH * $ratio);
            $maxScroll = $totalH - $visH;
            $frac = $maxScroll > 0 ? $scrollY / $maxScroll : 0.0;
            $thumbY = self::PAD + $frac * ($sbH - $thumbH);
            $trackColor = Color::rgba(0, 0, 0, 0.08);
            $thumbColor = Color::rgba(0, 0, 0, 0.28);
            $sbRad = $sbW / 2;
            $commands[] = new FillRoundedRect($sbX, self::PAD, $sbW, $sbH, $sbRad, $trackColor);
            $commands[] = new FillRoundedRect($sbX, $thumbY, $sbW, $thumbH, $sbRad, $thumbColor);
        }

        return new RenderCommandList($commands);
    }

    /**
     * Greedy word-wrap into visual lines, tracking each line's codepoint start
     * offset in the original string (newlines are hard breaks).
     *
     * Public so a {@see TextAreaControl} can reuse the exact same wrapping for
     * caret line-navigation without duplicating the measurement logic.
     *
     * @return array{0:list<string>, 1:list<int>}
     */
    public function wrap(string $text, float $maxW, float $fontSize): array
    {
        $lines = [];
        $starts = [];
        $cp = 0;
        $spaceW = $this->measure(' ', $fontSize);

        $hardLines = explode("\n", $text);
        foreach ($hardLines as $hl) {
            $words = preg_split('/\s+/u', $hl, -1, PREG_SPLIT_NO_EMPTY) ?? [];
            if ($words === []) {
                $lines[] = '';
                $starts[] = $cp;
            } else {
                $current = '';
                $currentW = 0.0;
                $currentStart = $cp;
                foreach ($words as $word) {
                    $ww = $this->measure($word, $fontSize);
                    if ($current === '') {
                        $current = $word;
                        $currentW = $ww;
                    } else {
                        $trial = $currentW + $spaceW + $ww;
                        if ($trial > $maxW) {
                            $lines[] = $current;
                            $starts[] = $currentStart;
                            $current = $word;
                            $currentW = $ww;
                            $currentStart = $cp;
                        } else {
                            $current .= ' ' . $word;
                            $currentW = $trial;
                        }
                    }
                    $cp += mb_strlen($word) + 1;
                }
                $lines[] = $current;
                $starts[] = $currentStart;
            }
            $cp += 1; // the hard newline
        }

        return [$lines, $starts];
    }

    /**
     * Map a codepoint cursor offset to (visualLineIndex, xWithinLine).
     *
     * @param list<string> $lines
     * @param list<int>    $starts
     * @return array{0:int, 1:float}
     */
    public function caretPosition(array $lines, array $starts, int $cursor, float $maxW, float $fontSize): array
    {
        $total = count($lines);
        $cursor = max(0, min($cursor, $this->totalCodepoints($lines, $starts)));

        // Find the last visual line whose start <= cursor.
        $line = 0;
        for ($i = 0; $i < $total; $i++) {
            $nextStart = $i + 1 < $total ? $starts[$i + 1] : $this->totalCodepoints($lines, $starts);
            if ($cursor >= $starts[$i] && $cursor < $nextStart) {
                $line = $i;
                break;
            }
            $line = $i;
        }

        $local = max(0, $cursor - $starts[$line]);
        $prefix = mb_substr($lines[$line], 0, $local);
        $x = $this->measure($prefix, $fontSize);

        return [$line, $x];
    }

    private function totalCodepoints(array $lines, array $starts): int
    {
        if ($lines === []) {
            return 0;
        }

        return $starts[count($lines) - 1] + mb_strlen($lines[count($lines) - 1]) + 1;
    }

    /**
     * Codepoint offset within a visual line whose measured x is closest to $x.
     * Used by a {@see TextAreaControl} to land the caret at the same column when
     * moving up/down between wrapped lines.
     */
    public function cursorOffsetForLine(float $x, int $line, array $lines, float $maxW, float $fontSize): int
    {
        $text = $lines[$line] ?? '';
        $len = mb_strlen($text);
        $acc = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($text, $i, 1);
            $w = $this->measure($ch, $fontSize);
            if ($acc + $w / 2 > $x) {
                break;
            }
            $acc += $w;
        }

        return $i;
    }

    /** Measure a string's width with a cached {@see TextLayout}. */
    private function measure(string $s, float $fontSize): float
    {
        if ($s === '') {
            return 0.0;
        }
        $key = $s . '|' . $fontSize;
        if (isset(self::$measureCache[$key])) {
            return self::$measureCache[$key];
        }

        // Guard: when called from TextAreaControl (wrap/caretPosition) before
        // any render() lifecycle, $this->tokens is null.  Fall back to defaults.
        if ($this->tokens === null) {
            $this->tokens = new DesignTokens();
        }

        $font = $this->tokens->font($fontSize);
        $str = new AttributedString();
        $str->append($s, Attribute::fromColor(Color::rgba(0, 0, 0, 1)), Attribute::size($fontSize));
        $layout = new TextLayout($str, $font, 1_000_000, DrawTextAlign::Left);
        [$w, ] = $layout->extents();

        self::$measureCache[$key] = $w;

        return $w;
    }

    private function paint(TextAreaSpec $spec, DesignTokens $tokens, string $path): Color
    {
        $c = $tokens->color($path);

        return $spec->enabled ? $c : Color::rgba($c->r, $c->g, $c->b, 0.4);
    }
}

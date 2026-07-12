<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\CommandExecutor\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\CommandExecutor\StrokeRoundedRect;
use Yangweijie\Ui2\Rendering\CommandExecutor\StrokeParams;
use Yangweijie\Ui2\Rendering\DesignTokens;

final class ReactionsRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'reactions';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $w, float $h): array
    {
        $spec = self::assertType($spec, ReactionsSpec::class);

        $cmds = [];
        $ph = $spec->pillHeight;
        $pillR = $ph / 2;
        $pillGap = 6.0;
        $pillPad = 10.0;
        $muted = $tokens->color('color.default');

        $count = \count($spec->reactions);
        if ($count === 0) {
            return $cmds;
        }

        // Calculate total width for centering
        $totalW = $count * ($pillPad * 2 + $spec->size) + ($count - 1) * $pillGap;
        $sx = ($w - $totalW) / 2;
        $sy = ($h - $ph) / 2;

        foreach ($spec->reactions as $i => $emoji) {
            $pillW = $pillPad * 2 + $spec->size;
            $px = $sx + $i * ($pillW + $pillGap);
            $isSelected = $spec->selected === $i;

            if ($isSelected) {
                $accent = $tokens->color('color.accent');
                $bg = $tokens->color('color.surface');
                $cmds[] = new FillRoundedRect($px, $sy, $pillW, $ph, $pillR, $bg);
                $cmds[] = new StrokeRoundedRect($px, $sy, $pillW, $ph, $pillR, $accent, new StrokeParams(thickness: 1.0));
            } else {
                $cmds[] = new FillRoundedRect($px, $sy, $pillW, $ph, $pillR, $muted);
            }
        }

        return $cmds;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $w, float $h): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $w, $h));
    }

    private static function assertType(WidgetSpec $spec, string $class): WidgetSpec
    {
        if (!$spec instanceof $class) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected %s, got %s', $class, $spec::class,
            ));
        }
        return $spec;
    }
}

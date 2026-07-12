<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\Color;
use Yangweijie\Ui2\Rendering\CommandExecutor\FillCircle;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\CommandExecutor\StrokeCircle;
use Yangweijie\Ui2\Rendering\CommandExecutor\StrokeLine;
use Yangweijie\Ui2\Rendering\CommandExecutor\StrokeParams;
use Yangweijie\Ui2\Rendering\DesignTokens;

final class StepperRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'stepper';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $w, float $h): array
    {
        $spec = self::assertType($spec, StepperSpec::class);

        $cmds = [];
        $r = $spec->size / 2;
        $totalW = $spec->steps * $spec->size + ($spec->steps - 1) * $spec->gap;
        $startX = ($w - $totalW) / 2 + $r;
        $cy = $h / 2;
        $accent = $tokens->color("color.{$spec->color}");
        $muted = $tokens->color('color.default');

        for ($i = 0; $i < $spec->steps; $i++) {
            $cx = $startX + $i * ($spec->size + $spec->gap);

            // Connector line to next step
            if ($i < $spec->steps - 1) {
                $nextX = $startX + ($i + 1) * ($spec->size + $spec->gap);
                $lineColor = $i < $spec->current ? $accent : $muted;
                $cmds[] = new StrokeLine($cx + 1, $cy, $nextX - 1, $cy, $lineColor, 2.0);
            }

            if ($i < $spec->current) {
                // Completed: filled accent circle
                $cmds[] = new FillCircle($cx, $cy, $r - 1, $accent);
            } elseif ($i === $spec->current) {
                // Current: hollow accent circle
                $cmds[] = new StrokeCircle($cx, $cy, $r - 1, $accent, new StrokeParams(thickness: 2.0));
            } else {
                // Future: hollow muted circle
                $cmds[] = new StrokeCircle($cx, $cy, $r - 1, $muted, new StrokeParams(thickness: 1.0));
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

<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\CommandExecutor\StrokeLine;
use Yangweijie\Ui2\Rendering\DesignTokens;

final class TimelineRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'timeline';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $w, float $h): array
    {
        $spec = self::assertType($spec, TimelineSpec::class);

        $lx = match ($spec->align) {
            'alternate' => $w / 2,
            default     => $spec->size / 2,
        };

        $color = $tokens->color("color.{$spec->color}");

        return [
            new StrokeLine($lx, 0.0, $lx, $h, $color, 1.0),
        ];
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

<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\CommandExecutor\FillCircle;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\DesignTokens;

final class TimelineItemRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'timeline_item';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $w, float $h): array
    {
        $spec = self::assertType($spec, TimelineItemSpec::class);

        $dotR = 4.0;
        $color = $tokens->color("color.{$spec->color}");

        return [
            new FillCircle(12.0, $h / 2, $dotR, $color),
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

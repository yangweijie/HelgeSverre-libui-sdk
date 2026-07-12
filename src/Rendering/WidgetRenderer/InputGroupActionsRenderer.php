<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\DesignTokens;

final class InputGroupActionsRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'input_group_actions';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $w, float $h): array
    {
        self::assertType($spec, InputGroupActionsSpec::class);
        return [];
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

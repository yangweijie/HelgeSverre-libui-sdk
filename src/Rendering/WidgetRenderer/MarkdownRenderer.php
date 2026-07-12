<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\Color;
use Yangweijie\Ui2\Rendering\CommandExecutor\DrawText;
use Yangweijie\Ui2\Rendering\CommandExecutor\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\DesignTokens;

final class MarkdownRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'markdown';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $w, float $h): array
    {
        $spec = self::assertType($spec, MarkdownSpec::class);

        $cmds = [];

        // Background
        if ($spec->bg !== 'transparent') {
            $bg = $tokens->color("color.{$spec->bg}");
            $cmds[] = new FillRoundedRect(0.0, 0.0, $w, $h, $spec->radius, $bg);
        }

        return $cmds;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $w, float $h): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $w, $h));
    }

    /**
     * @template T of WidgetSpec
     * @param  class-string<T> $class
     * @return T
     */
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

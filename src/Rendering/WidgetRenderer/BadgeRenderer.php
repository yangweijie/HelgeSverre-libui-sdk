<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

final class BadgeRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'badge';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof BadgeSpec) {
            return [];
        }

        $alpha = match ($spec->variant) {
            'subtle'  => 0.12,
            'outline' => 0.0,
            default   => 1.0,
        };

        $commands = [];
        if ($alpha > 0.0) {
            $base = $tokens->color("color.{$spec->color}");
            $bg = Color::rgba($base->r, $base->g, $base->b, $alpha);
            $commands[] = new FillRoundedRect(
                x: 0.0,
                y: 0.0,
                width: $width,
                height: $height,
                radius: $height / 2.0,
                color: $bg,
            );
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }


}

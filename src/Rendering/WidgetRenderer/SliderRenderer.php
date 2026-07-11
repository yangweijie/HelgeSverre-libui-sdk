<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Libui\Draw\StrokeParams;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillCircle;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeCircle;

/**
 * Self-drawn horizontal slider: a track, a primary-filled portion, and a thumb.
 *
 * Hover/disabled feedback is consumed from the token system (shared
 * {@see TokenWash} trait) so it matches ButtonRenderer exactly.
 */
final class SliderRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'slider';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof SliderSpec) {
            throw new \InvalidArgumentException('SliderRenderer requires a SliderSpec');
        }

        $trackH = min($height, 6.0);
        $trackY = ($height - $trackH) / 2;
        $value = max(0.0, min(1.0, $spec->value));
        $fillW = max($trackH, $width * $value);

        $primary = $this->paint($spec, $tokens, 'color.primary');
        $track = $tokens->color('color.track');

        $commands = [];
        $commands[] = new FillRoundedRect(0, $trackY, $width, $trackH, $trackH / 2, $track);
        $commands[] = new FillRoundedRect(0, $trackY, $fillW, $trackH, $trackH / 2, $primary);

        $thumbR = min($height, 16.0) / 2;
        $thumbX = $fillW;
        $thumbY = $height / 2;
        $thumbColor = $spec->enabled ? $tokens->color('color.surface') : $track;
        $commands[] = new FillCircle($thumbX, $thumbY, $thumbR, $thumbColor);
        $commands[] = new StrokeCircle($thumbX, $thumbY, $thumbR, $primary, StrokeParams::solid(2.0));

        // Token-driven hover/disabled wash over the whole slider (pill radius).
        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $height / 2) as $washCmd) {
            $commands[] = $washCmd;
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }

    private function paint(SliderSpec $spec, DesignTokens $tokens, string $path): Color
    {
        $c = $tokens->color($path);
        if (! $spec->enabled) {
            return Color::rgba($c->r, $c->g, $c->b, 0.4);
        }
        if ($spec->pressed) {
            return Color::rgba($c->r * 0.85, $c->g * 0.85, $c->b * 0.85, $c->a);
        }

        return $c;
    }
}

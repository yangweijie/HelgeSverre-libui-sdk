<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Self-drawn linear progress bar: a track with a primary-filled portion.
 *
 * Hover/disabled feedback is consumed from the token system (shared
 * {@see TokenWash} trait) so it matches ButtonRenderer exactly. A progress bar
 * is normally non-interactive, so its wash is effectively the disabled mute.
 */
final class ProgressRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'progress';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof ProgressSpec) {
            throw new \InvalidArgumentException('ProgressRenderer requires a ProgressSpec');
        }

        $value = max(0.0, min(1.0, $spec->value));
        $fillW = max($height, $width * $value);
        $primary = $this->paint($spec, $tokens, 'color.primary');
        $track = $tokens->color('color.track');

        $commands = [
            new FillRoundedRect(0, 0, $width, $height, $spec->radius, $track),
            new FillRoundedRect(0, 0, $fillW, $height, $spec->radius, $primary),
        ];

        // Token-driven hover/disabled wash over the whole bar.
        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }

    private function paint(ProgressSpec $spec, DesignTokens $tokens, string $path): Color
    {
        $c = $tokens->color($path);

        return $spec->enabled ? $c : Color::rgba($c->r, $c->g, $c->b, 0.4);
    }
}

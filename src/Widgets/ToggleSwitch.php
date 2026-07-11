<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Brush;
use Libui\Control;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Draw\StrokeParams;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;
use Yangweijie\Ui2\Rendering\DesignTokens;

/**
 * A custom-drawn toggle switch, rendered via an Area.
 *
 * ```php
 * $toggle = new ToggleSwitch(false);
 * $toggle->on('change', fn (bool $on) => print($on ? 'ON' : 'OFF'));
 * ```
 *
 * @implements HasValue<bool>
 */
class ToggleSwitch extends Composite
{
    use EmitsEvents;

    public const WIDTH = 40;
    public const HEIGHT = 22;
    public const KNOB_RADIUS = 8;

    private readonly Area $area;
    private readonly ToggleDelegate $delegate;
    public DesignTokens $tokens;

    public function __construct(bool $initialValue = false, ?DesignTokens $tokens = null)
    {
        $this->tokens = $tokens ?? new DesignTokens();
        $this->delegate = new ToggleDelegate($initialValue);
        $this->delegate->tokens = $this->tokens;
        $this->area = new Area($this->delegate);

        $this->delegate->onChange = function (bool $value): void {
            $this->emit('change', $value);
        };
    }

    public function root(): Control
    {
        return $this->area;
    }

    public function value(): bool
    {
        return $this->delegate->on;
    }

    public function setValue(mixed $value): static
    {
        $this->delegate->on = (bool) $value;
        $this->delegate->redraw();
        return $this;
    }

    /**
     * Return the active design tokens.
     */
    public function getTokens(): DesignTokens
    {
        return $this->tokens;
    }

    /**
     * Apply a theme override (deep-merged on top of the current tokens) and
     * repaint. The previous token set is never mutated.
     *
     * @param array<string, mixed> $overrides
     */
    public function setTheme(array $overrides): static
    {
        $this->tokens = $this->tokens->applyTheme($overrides);
        $this->delegate->tokens = $this->tokens;
        $this->delegate->redraw();
        return $this;
    }
}

/**
 * @internal Area delegate driving the toggle's custom drawing.
 */
final class ToggleDelegate extends AreaDelegate
{
    /** @var callable(bool):void|null */
    public $onChange = null;

    public bool $on;
    public DesignTokens $tokens;

    /** Track whether we are mid-drag. */
    private bool $dragging = false;

    public function __construct(bool $initialValue)
    {
        $this->on = $initialValue;
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $w = ToggleSwitch::WIDTH;
        $h = ToggleSwitch::HEIGHT;
        $r = $h / 2;
        $knobR = ToggleSwitch::KNOB_RADIUS;
        $ox = ($params->areaWidth - $w) / 2;
        $oy = ($params->areaHeight - $h) / 2;

        $bgColor = $this->on ? $this->tokens->color('color.toggleOn') : $this->tokens->color('color.toggleOff');
        $ctx->fillRoundedRect($ox, $oy, $w, $h, $r, $bgColor);

        $borderColor = $this->tokens->color('color.toggleBorder');
        $ctx->strokeRoundedRect($ox, $oy, $w, $h, $r, $borderColor, StrokeParams::solid(1.0));

        $knobX = $ox + ($this->on ? $w - $knobR - 3 : $knobR + 3);
        $knobY = $oy + $h / 2;
        $knobColor = $this->tokens->color('color.knob');
        $ctx->fillCircle($knobX, $knobY, $knobR, $knobColor);

        $ctx->strokeCircle($knobX, $knobY, $knobR, $this->tokens->color('color.knobBorder'), StrokeParams::solid(0.5));
    }

    public function mouse(AreaMouseEvent $event): void
    {
        if ($event->isLeftButtonDown()) {
            $this->dragging = true;
            $this->toggle();
        } elseif ($this->dragging && !$event->isLeftButtonDown()) {
            $this->dragging = false;
        }
    }

    private function toggle(): void
    {
        $this->on = !$this->on;
        $this->redraw();
        if ($this->onChange !== null) {
            ($this->onChange)($this->on);
        }
    }
}

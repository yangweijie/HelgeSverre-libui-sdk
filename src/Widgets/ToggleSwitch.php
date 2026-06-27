<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Brush;
use Libui\Color;
use Libui\Control;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Draw\StrokeParams;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

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

    public function __construct(bool $initialValue = false)
    {
        $this->delegate = new ToggleDelegate($initialValue);
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
}

/**
 * @internal Area delegate driving the toggle's custom drawing.
 */
final class ToggleDelegate extends AreaDelegate
{
    /** @var callable(bool):void|null */
    public $onChange = null;

    public bool $on;

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

        $bgColor = $this->on ? Color::rgba(0.2, 0.6, 1.0, 1.0) : Color::rgba(0.5, 0.5, 0.5, 0.4);
        $ctx->fillRoundedRect($ox, $oy, $w, $h, $r, $bgColor);

        $borderColor = Color::rgba(0.3, 0.3, 0.3, 0.6);
        $ctx->strokeRoundedRect($ox, $oy, $w, $h, $r, $borderColor, StrokeParams::solid(1.0));

        $knobX = $ox + ($this->on ? $w - $knobR - 3 : $knobR + 3);
        $knobY = $oy + $h / 2;
        $knobColor = Color::rgba(1.0, 1.0, 1.0, 1.0);
        $ctx->fillCircle($knobX, $knobY, $knobR, $knobColor);

        $ctx->strokeCircle($knobX, $knobY, $knobR, Color::rgba(0.2, 0.2, 0.2, 0.3), StrokeParams::solid(0.5));
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

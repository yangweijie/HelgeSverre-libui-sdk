<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Control;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaMouseEvent;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;
use Yangweijie\Ui2\Rendering\CommandExecutor;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;

/**
 * A self-drawn push button driven by the WidgetRenderer registry.
 *
 * The visual is produced by the registered {@see ButtonRenderer} (from the
 * default registry), so the button inherits the same theming and retained
 * command model used by CircleProgressBar / ToggleSwitch. When the registry
 * has no renderer for "button" — or when $preferNative is true — it falls back
 * to the native libui Button. The public API (label / enabled / theme / click
 * event) is identical either way, so callers opt into the self-drawn look
 * without changing how they use the control.
 *
 * ```php
 * $btn = new RendererButton('保存', 'filled');
 * $btn->on('click', fn () => save());
 * $window->add($btn->root());
 * ```
 *
 * Layout note (libui quirk): an Area has NO intrinsic size, so a bare Area in a
 * non-stretchy box slot collapses. We therefore expose a *non-scrolling* Area
 * (exactly like ToggleSwitch / StatusIndicator) and rely on the caller placing
 * it stretchy in its container — the Area then fills the allotted space, the
 * renderer paints the button to fill that space, and mouse hits map 1:1 to the
 * visible button. This avoids the Group/title hacks entirely (no border, no
 * duplicate caption) and is the same pattern the other self-drawn widgets use.
 */
class RendererButton extends Composite
{
    use EmitsEvents;

    public const WIDTH = 120;
    public const HEIGHT = 36;

    private Control $rootControl;
    private ?Area $area = null;
    private ?RendererButtonDelegate $delegate = null;
    private ?\Libui\Button $native = null;

    public DesignTokens $tokens;
    private RendererRegistry $registry;
    private bool $useNative;
    private string $label;
    private string $variant;
    private bool $enabled = true;

    public function __construct(
        string $label = '',
        string $variant = 'filled',
        bool $enabled = true,
        ?DesignTokens $tokens = null,
        ?RendererRegistry $registry = null,
        bool $preferNative = false,
    ) {
        $this->label = $label;
        $this->variant = $variant;
        $this->enabled = $enabled;
        $this->tokens = $tokens ?? new DesignTokens();
        $this->registry = $registry ?? RendererRegistry::default();

        // Fallback decision: explicit native preference, or no renderer registered.
        $this->useNative = $preferNative || ! $this->registry->has('button');

        if ($this->useNative) {
            $this->native = new \Libui\Button($label);
            $this->native->onClicked(function (): void {
                $this->emit('click', $this);
            });
            $this->rootControl = $this->native;
        } else {
            // Non-scrolling Area, same as ToggleSwitch / StatusIndicator. It has
            // no intrinsic size, so the caller MUST place it stretchy (e.g. via
            // Build::stretchy($btn->root())) for it to get a real footprint.
            $this->delegate = new RendererButtonDelegate($this);
            $this->delegate->tokens = $this->tokens;
            $this->area = new Area($this->delegate);
            $this->rootControl = $this->area;
        }
    }

    public function root(): Control
    {
        return $this->rootControl;
    }

    /**
     * True when the native libui Button is used instead of the self-drawn renderer.
     */
    public function isNative(): bool
    {
        return $this->useNative;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function variant(): string
    {
        return $this->variant;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function registry(): RendererRegistry
    {
        return $this->registry;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        if ($this->useNative) {
            $this->native->setText($label);
        } else {
            $this->delegate->redraw();
        }

        return $this;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        if (! $this->useNative) {
            $this->delegate->redraw();
        }

        return $this;
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
        if (! $this->useNative) {
            $this->delegate->tokens = $this->tokens;
            $this->delegate->redraw();
        }

        return $this;
    }

    /**
     * @internal Fired by the delegate on a completed press-release inside the button.
     */
    public function fireClick(): void
    {
        $this->emit('click', $this);
    }
}

/**
 * @internal Area delegate that drives the renderer-based button drawing.
 */
final class RendererButtonDelegate extends AreaDelegate
{
    public DesignTokens $tokens;
    private bool $pressed = false;

    public function __construct(private readonly RendererButton $owner)
    {
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $renderer = $this->owner->registry()->get('button');
        if ($renderer === null) {
            return;
        }

        $spec = new ButtonSpec(
            label: $this->owner->label(),
            variant: $this->owner->variant(),
            enabled: $this->owner->enabled(),
            pressed: $this->pressed,
        );

        // Paint the button to fill whatever space the container gave the Area.
        $list = $renderer->render($spec, $this->tokens, $params->areaWidth, $params->areaHeight);
        (new CommandExecutor())->execute($ctx, $list);
        $list->free();
    }

    public function mouse(AreaMouseEvent $event): void
    {
        // The whole Area is the button, so a press/release inside it is a click.
        $inside = $event->x >= 0 && $event->x <= $event->areaWidth
            && $event->y >= 0 && $event->y <= $event->areaHeight;

        if ($event->down === 1 && $inside) {
            $this->pressed = true;
            $this->redraw();
        } elseif ($event->up === 1) {
            $clicked = $this->pressed && $inside;
            $this->pressed = false;
            $this->redraw();
            if ($clicked) {
                $this->owner->fireClick();
            }
        }
    }
}

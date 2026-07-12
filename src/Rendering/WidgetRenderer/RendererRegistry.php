<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Maps a widget type name to its {@see WidgetRenderer}.
 *
 * A widget collects its state into a {@see WidgetSpec}, then asks the registry
 * for the renderer that knows how to draw that type. When no renderer is
 * registered for a type, {@see get()} returns null — that null is the signal
 * for the widget to fall back to its native libui control instead of a
 * self-drawn one.
 *
 *     $renderer = $registry->get('button');
 *     if ($renderer === null) {
 *         // fall back to the native control
 *     }
 */
final class RendererRegistry
{
    /** @var array<string, WidgetRenderer> */
    private array $renderers = [];

    public function register(WidgetRenderer $renderer): void
    {
        $this->renderers[$renderer::type()] = $renderer;
    }

    /**
     * True when a renderer is registered for $type.
     */
    public function has(string $type): bool
    {
        return isset($this->renderers[$type]);
    }

    /**
     * Returns the renderer for $type, or null when nothing is registered
     * (the caller's cue to use the native control).
     */
    public function get(string $type): ?WidgetRenderer
    {
        return $this->renderers[$type] ?? null;
    }

    /**
     * @return list<string> All registered widget types.
     */
    public function types(): array
    {
        return array_keys($this->renderers);
    }

    /**
     * A registry pre-loaded with the built-in self-drawn widget renderers.
     */
    public static function default(): self
    {
        $registry = new self();
        $registry->register(new ButtonRenderer());
        $registry->register(new CardRenderer());
        $registry->register(new CheckboxRenderer());
        $registry->register(new RadioRenderer());
        $registry->register(new SliderRenderer());
        $registry->register(new ProgressRenderer());
        $registry->register(new TextFieldRenderer());
        $registry->register(new SelectRenderer());
        $registry->register(new ListRowRenderer());
        $registry->register(new TableRowRenderer());
        $registry->register(new TabRenderer());
        $registry->register(new DialogCardRenderer());
        $registry->register(new DialogBodyRenderer());
        $registry->register(new BreadcrumbItemRenderer());
        $registry->register(new PaginationItemRenderer());
        $registry->register(new SearchFieldRenderer());
        $registry->register(new ScrollViewRenderer());
        $registry->register(new TextAreaRenderer());
        $registry->register(new LabelRenderer());
        $registry->register(new PanelRenderer());
        $registry->register(new ImageRenderer());
        $registry->register(new ScrimRenderer());

        return $registry;
    }
}

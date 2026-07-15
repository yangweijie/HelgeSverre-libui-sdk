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
        $registry->register(new NumberRenderer());
        $registry->register(new PasswordRenderer());
        $registry->register(new DatePickerRenderer());
        $registry->register(new FilePickerRenderer());
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
        $registry->register(new StackRenderer());
        $registry->register(new GridRenderer());
        $registry->register(new BadgeRenderer());
        $registry->register(new SeparatorRenderer());
        $registry->register(new IconRenderer());
        $registry->register(new ButtonGroupRenderer());
        $registry->register(new RadioGroupRenderer());
        $registry->register(new ToggleGroupRenderer());
        $registry->register(new TooltipRenderer());
        $registry->register(new MenuItemRenderer());
        $registry->register(new StatusBarRenderer());
        $registry->register(new SwitchRenderer());
        $registry->register(new InputRenderer());
        $registry->register(new SkeletonRenderer());
        $registry->register(new SpinnerRenderer());
        $registry->register(new SplitRenderer());
        $registry->register(new AlertRenderer());
        $registry->register(new AccordionRenderer());
        $registry->register(new ToggleButtonRenderer());
        $registry->register(new TableCellRenderer());
        $registry->register(new ResizableRenderer());
        $registry->register(new BubbleRenderer());
        $registry->register(new ContextMenuRenderer());
        $registry->register(new MarkdownRenderer());
        $registry->register(new StepperRenderer());
        $registry->register(new StepRenderer());
        $registry->register(new TimelineRenderer());
        $registry->register(new TimelineItemRenderer());
        $registry->register(new InputGroupRenderer());
        $registry->register(new InputGroupActionsRenderer());
        $registry->register(new SpanRenderer());
        $registry->register(new ReactionsRenderer());
        $registry->register(new ChartRenderer());
        $registry->register(new CanvasRenderer());
        $registry->register(new WebViewRenderer());

        return $registry;
    }
}

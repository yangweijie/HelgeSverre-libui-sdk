<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Semantics;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\WidgetSpec;

/**
 * An accessible description of one layout node, equivalent to an ARIA node.
 *
 * Built from a {@see LayoutNode} tree via {@see self::fromLayout()}:
 *
 *  - role is taken from the node's explicit {@see WidgetRole} if set, otherwise
 *    derived from the leaf spec's {@see WidgetSpec::type()} (see {@see mapType}).
 *  - state (label, value, checked, enabled …) is lifted from the spec's public
 *    readonly fields, so no per-widget branching is needed in callers.
 *  - geometry (x/y/w/h) is copied so a platform bridge can map a role to a
 *    screen rectangle for hit-testing / VoiceOver cursors.
 *
 * The tree is pure data — it can be built headless (no libui control needed),
 * which is exactly how it is tested.
 */
final class SemanticsNode
{
    public ?string $id;
    public WidgetRole $role;

    public ?string $label = null;
    public ?string $value = null;

    public bool $enabled = true;
    public ?bool $checked = null;
    public ?bool $selected = null;

    /** Whether this node participates in Tab navigation (has an id + a spec). */
    public bool $focusable = false;
    public bool $focused = false;

    /** valueNow / valueMin / valueMax for range roles (slider, progressbar). */
    public ?float $valueNow = null;
    public ?float $valueMin = null;
    public ?float $valueMax = null;

    public float $x = 0.0;
    public float $y = 0.0;
    public float $w = 0.0;
    public float $h = 0.0;

    /** @var list<self> */
    public array $children = [];

    public function __construct(?string $id, WidgetRole $role)
    {
        $this->id = $id;
        $this->role = $role;
    }

    /** Append a child node and return $this for chaining. */
    public function add(self $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    /** Build the full semantics tree rooted at $node (recursive). */
    public static function fromLayout(LayoutNode $node, ?WidgetRole $forceRole = null): self
    {
        $role = $forceRole ?? self::roleFor($node);
        $sem = new self($node->id, $role);
        $sem->x = $node->x;
        $sem->y = $node->y;
        $sem->w = $node->w;
        $sem->h = $node->h;

        self::applyState($sem, $node);

        foreach ($node->children as $child) {
            $sem->add(self::fromLayout($child));
        }

        return $sem;
    }

    /**
     * Map a renderer discriminator (WidgetSpec::type()) to a semantic role.
     *
     * The keys follow the widget catalogue agreed for this SDK:
     *   button → button, checkbox → checkbox, radio → radio, slider → slider,
     *   progress → progressbar, text_field → textbox, select → combobox,
     *   card → group (a visual container, no interactive semantics).
     */
    public static function mapType(string $type): WidgetRole
    {
        return match ($type) {
            'button'   => WidgetRole::Button,
            'checkbox' => WidgetRole::Checkbox,
            'radio'    => WidgetRole::Radio,
            'slider'   => WidgetRole::Slider,
            'progress' => WidgetRole::ProgressBar,
            'text_field' => WidgetRole::TextBox,
            'search_field' => WidgetRole::TextBox,
            'select'   => WidgetRole::ComboBox,
            'card'     => WidgetRole::Group,
            'list_row' => WidgetRole::ListItem,
            'table_row' => WidgetRole::ListItem,
            'tab'      => WidgetRole::Tab,
            'dialog_card' => WidgetRole::Dialog,
            'dialog_body' => WidgetRole::Group,
            'breadcrumb_item' => WidgetRole::ListItem,
            'pagination_item' => WidgetRole::Button,
            'text_area' => WidgetRole::TextBox,
            'scroll_view' => WidgetRole::Group,
            default    => WidgetRole::Group,
        };
    }

    private static function roleFor(LayoutNode $node): WidgetRole
    {
        if ($node->role !== null) {
            return $node->role;
        }

        if ($node->spec !== null) {
            return self::mapType($node->spec->type());
        }

        return WidgetRole::Group;
    }

    private static function applyState(self $sem, LayoutNode $node): void
    {
        $spec = $node->spec;

        $sem->focusable = $node->id !== null && $spec !== null;
        $sem->focused = $node->hovered;

        if ($spec === null) {
            return;
        }

        $sem->label = self::labelOf($spec);
        $sem->enabled = self::flagOf($spec, 'enabled', true);

        if (self::has($spec, 'checked')) {
            $sem->checked = $spec->checked;
        }
        if (self::has($spec, 'selected')) {
            $sem->selected = $spec->selected;
        }
        if (self::has($spec, 'value')) {
            $v = $spec->value;
            $sem->value = (string) $v;
            if (is_numeric($v)) {
                $sem->valueNow = (float) $v;
                if (self::has($spec, 'min')) {
                    $sem->valueMin = (float) $spec->min;
                }
                if (self::has($spec, 'max')) {
                    $sem->valueMax = (float) $spec->max;
                }
            }
        }
    }

    /** Prefer an explicit $label, fall back to $placeholder, else null. */
    private static function labelOf(WidgetSpec $spec): ?string
    {
        if (self::has($spec, 'label') && $spec->label !== '') {
            return $spec->label;
        }
        if (self::has($spec, 'placeholder') && $spec->placeholder !== '') {
            return $spec->placeholder;
        }

        return null;
    }

    private static function flagOf(WidgetSpec $spec, string $name, bool $default): bool
    {
        return self::has($spec, $name) ? (bool) $spec->{$name} : $default;
    }

    private static function has(WidgetSpec $spec, string $name): bool
    {
        return property_exists($spec, $name);
    }

    /**
     * Build a container node from a list of SemanticProvider children
     * (typically libui Controls). Children whose semantics() is null are
     * skipped. Returns null when no child contributes a node.
     *
     * @param  iterable<SemanticProvider> $controls
     */
    public static function fromControls(iterable $controls, WidgetRole $role, ?string $label = null, ?string $id = null): ?self
    {
        $node = new self($id, $role);
        $node->label = $label;

        foreach ($controls as $control) {
            if ($control instanceof SemanticProvider) {
                $child = $control->semantics();
                if ($child !== null) {
                    $node->add($child);
                }
            }
        }

        return $node->children === [] ? null : $node;
    }

    /**
     * Serialize the whole tree (id / role / label / value / state / geometry /
     * children) into a plain array an automation server can JSON-encode.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'role'      => $this->role->value,
            'label'     => $this->label,
            'value'     => $this->value,
            'enabled'   => $this->enabled,
            'checked'   => $this->checked,
            'selected'  => $this->selected,
            'focusable' => $this->focusable,
            'focused'   => $this->focused,
            'valueNow'  => $this->valueNow,
            'valueMin'  => $this->valueMin,
            'valueMax'  => $this->valueMax,
            'rect'      => [
                'x' => $this->x,
                'y' => $this->y,
                'w' => $this->w,
                'h' => $this->h,
            ],
            'children'  => array_map(static fn (self $c): array => $c->toArray(), $this->children),
        ];
    }

    /** JSON form of {@see toArray()}. */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }
}

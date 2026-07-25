<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

use Yangweijie\Ui2\Layout\FlexLayout;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\BadgeSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonGroupSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\InputSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\WidgetSpec;
use Yangweijie\Ui2\Semantics\SemanticsNode;

/**
 * Runtime inspector for the toolkit's self-drawn component tree.
 *
 * Wraps a {@see LayoutNode} root (typically a {@see \Yangweijie\Ui2\Widgets\Surface}
 * root layout) and exposes an F12-style contract: a serialisable component tree
 * with geometry, per-node style (LayoutStyle) and widget properties (WidgetSpec
 * public fields), hit-testing, and live mutation (change a style / property,
 * restructure children) that triggers a repaint via an injected redraw callback.
 *
 * The class is pure data + logic — no libui / FFI — so every method is
 * headless-testable. Live usage injects callbacks that forward to a Surface
 * (see {@see self::forSurface}); tests inject a plain LayoutNode root.
 */
final class ComponentInspector
{
    /** Node fields that are transient interaction state, not user-editable props. */
    private const RUNTIME_PROPS = ['pressed', 'hovered'];

    private ?string $selectedId = null;
    private ?string $highlightId = null;
    private bool $pickMode = false;

    /**
     * @param LayoutNode      $root           Component tree root.
     * @param float           $width          Layout viewport width (for headless geometry).
     * @param float           $height         Layout viewport height.
     * @param (callable():void)|null $redraw  Repaint hook (live: Surface::redraw).
     * @param (callable(string):?array<int,float>)|null $rectResolver Viewport rect of a node id.
     * @param (callable(?string):void)|null   $highlightSetter Sets the on-screen highlight.
     * @param (callable(bool):void)|null      $modeSetter      Enables/disables pick mode.
     */
    public function __construct(
        private LayoutNode $root,
        private float $width = 800.0,
        private float $height = 600.0,
        private $redraw = null,
        private $rectResolver = null,
        private $highlightSetter = null,
        private $modeSetter = null,
    ) {
    }

    /** Build an inspector bound to a live Surface (forwards to its hooks). */
    public static function forSurface(object $surface, float $width = 800.0, float $height = 600.0): self
    {
        $inspector = new self(
            $surface->rootLayout(),
            $width,
            $height,
            fn () => $surface->redraw(),
            fn (string $id): ?array => $surface->screenRectOf($id),
            fn (?string $id) => $surface->setInspectorHighlight($id),
            fn (bool $on) => $surface->setInspectorMode($on),
        );
        // In-window click-to-pick: the Surface forwards the click coordinates to
        // pickAt() so the topmost node under the cursor is selected + highlighted.
        $surface->setInspectorPickHandler(fn (float $x, float $y) => $inspector->pickAt($x, $y));

        return $inspector;
    }

    // ---------------------------------------------------------------- snapshot

    /** Full component tree with geometry, style and widget properties. */
    public function snapshot(): array
    {
        FlexLayout::layout($this->root, 0, 0, $this->width, $this->height);

        return [
            'selected' => $this->selectedId,
            'viewport' => ['w' => $this->width, 'h' => $this->height],
            'root' => $this->nodeToArray($this->root),
        ];
    }

    private function nodeToArray(LayoutNode $node): array
    {
        $type = $node->spec !== null ? $node->spec->type() : 'container';
        $rect = $this->resolveRect($node);

        return [
            'id' => $node->id,
            'type' => $type,
            'role' => $node->spec !== null
                ? SemanticsNode::mapType($node->spec->type())->value
                : ($node->role?->value ?? 'group'),
            'rect' => ['x' => $rect[0], 'y' => $rect[1], 'w' => $rect[2], 'h' => $rect[3]],
            'style' => $this->styleToArray($node),
            'props' => $this->propsToArray($node),
            'children' => array_map(fn (LayoutNode $c) => $this->nodeToArray($c), $node->children),
        ];
    }

    /** @return array{0:float,1:float,2:float,3:float} */
    private function resolveRect(LayoutNode $node): array
    {
        if ($this->rectResolver !== null) {
            $r = ($this->rectResolver)($node->id ?? '');
            if ($r !== null) {
                return $r;
            }
        }

        return [$node->x, $node->y, $node->w, $node->h];
    }

    /** @return array<string, mixed> */
    private function styleToArray(LayoutNode $node): array
    {
        $out = [];
        foreach (get_object_vars($node->style) as $k => $v) {
            $out[$k] = $v;
        }

        return $out;
    }

    /** @return array<int, array{name:string, value:mixed, editable:bool}> */
    private function propsToArray(LayoutNode $node): array
    {
        $out = [];
        // Node-level "attributes" first.
        $out[] = ['name' => 'id', 'value' => $node->id, 'editable' => true];
        $out[] = [
            'name' => 'role',
            'value' => $node->role?->value,
            'editable' => $node->role !== null,
        ];

        if ($node->spec !== null) {
            foreach (get_object_vars($node->spec) as $k => $v) {
                if (in_array($k, self::RUNTIME_PROPS, true)) {
                    continue;
                }
                $out[] = ['name' => $k, 'value' => $v, 'editable' => true];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------- selection

    public function selectedId(): ?string
    {
        return $this->selectedId;
    }

    /** Select a node by id, mark it highlighted, and repaint. */
    public function pick(string $id): ?string
    {
        if (LayoutNode::find($this->root, $id) === null) {
            return null;
        }
        $this->selectedId = $id;
        $this->highlight($id);

        return $id;
    }

    /** Hit-test viewport coordinates; selects + highlights the topmost node. */
    public function pickAt(float $x, float $y): ?string
    {
        $id = LayoutNode::findAt($this->root, $x, $y);
        if ($id !== null) {
            $this->pick($id);
        }

        return $id;
    }

    public function highlight(?string $id): void
    {
        $this->highlightId = $id;
        if ($this->highlightSetter !== null) {
            ($this->highlightSetter)($id);
        }
    }

    public function setPickMode(bool $on): void
    {
        $this->pickMode = $on;
        if ($this->modeSetter !== null) {
            ($this->modeSetter)($on);
        }
    }

    public function isPickMode(): bool
    {
        return $this->pickMode;
    }

    public function highlightId(): ?string
    {
        return $this->highlightId;
    }

    // ------------------------------------------------------------------ detail

    /** Per-node detail for the right-hand inspector panel. */
    public function getNode(string $id): ?array
    {
        $node = LayoutNode::find($this->root, $id);
        if ($node === null) {
            return null;
        }

        $rect = $this->resolveRect($node);

        return [
            'id' => $node->id,
            'type' => $node->spec !== null ? $node->spec->type() : 'container',
            'rect' => ['x' => $rect[0], 'y' => $rect[1], 'w' => $rect[2], 'h' => $rect[3]],
            'style' => $this->styleToArray($node),
            'props' => $this->propsToArray($node),
            'children' => array_map(
                fn (LayoutNode $c) => [
                    'id' => $c->id,
                    'type' => $c->spec !== null ? $c->spec->type() : 'container',
                ],
                $node->children,
            ),
            'canDelete' => $node !== $this->root,
        ];
    }

    // ---------------------------------------------------------------- mutators

    /** Edit a LayoutStyle field (typed cast applied). */
    public function setStyle(string $id, string $prop, mixed $value): bool
    {
        $node = LayoutNode::find($this->root, $id);
        if ($node === null || ! property_exists($node->style, $prop)) {
            return false;
        }
        $this->setField($node->style, $prop, $value);
        $this->repaint();

        return true;
    }

    /** Edit (or, when the node has no spec, a node-level) a widget property. */
    public function setAttr(string $id, string $name, mixed $value): bool
    {
        $node = LayoutNode::find($this->root, $id);
        if ($node === null) {
            return false;
        }
        if ($node->spec !== null && property_exists($node->spec, $name)) {
            $node->spec = $this->rebuiltSpec($node->spec, $name, $this->castField($node->spec, $name, $value));
            $this->repaint();

            return true;
        }
        if (property_exists($node, $name)) {
            $this->setField($node, $name, $value);
            $this->repaint();

            return true;
        }

        return false;
    }

    /** Reset a widget/node property to its type default. */
    public function deleteAttr(string $id, string $name): bool
    {
        $node = LayoutNode::find($this->root, $id);
        if ($node === null) {
            return false;
        }
        if ($node->spec !== null && property_exists($node->spec, $name)) {
            $def = $this->typeDefault($this->fieldType($node->spec, $name));
            $node->spec = $this->rebuiltSpec($node->spec, $name, $def);
            $this->repaint();

            return true;
        }
        if (property_exists($node, $name)) {
            $this->setField($node, $name, $this->typeDefault($this->fieldType($node, $name)));
            $this->repaint();

            return true;
        }

        return false;
    }

    /** Append a child leaf of the given widget type. */
    public function addChild(string $id, string $type): bool
    {
        $node = LayoutNode::find($this->root, $id);
        if ($node === null) {
            return false;
        }
        $childId = $this->uniqueId($node, $type);
        $node->children[] = LayoutNode::leaf($childId, $this->makeSpec($type));
        $this->repaint();

        return true;
    }

    /** Remove a node (never the root). */
    public function deleteNode(string $id): bool
    {
        if ($id === $this->root->id) {
            return false;
        }
        $parent = $this->parentOf($this->root, $id);
        if ($parent === null) {
            return false;
        }
        $parent->children = array_values(array_filter(
            $parent->children,
            fn (LayoutNode $c) => $c->id !== $id,
        ));
        if ($this->selectedId === $id) {
            $this->selectedId = null;
            $this->highlight(null);
        }
        $this->repaint();

        return true;
    }

    // ----------------------------------------------------------------- helpers

    private function repaint(): void
    {
        if ($this->redraw !== null) {
            ($this->redraw)();
        }
    }

    private function parentOf(LayoutNode $node, string $id): ?LayoutNode
    {
        foreach ($node->children as $child) {
            if ($child->id === $id) {
                return $node;
            }
            $found = $this->parentOf($child, $id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function uniqueId(LayoutNode $scope, string $type): string
    {
        $base = $type . '_' . (count($scope->children) + 1);
        $candidate = $base;
        $i = 2;
        while (LayoutNode::find($this->root, $candidate) !== null) {
            $candidate = $base . '_' . ($i++);
        }

        return $candidate;
    }

    /** Set a mutable (non-readonly) field via Reflection + typed cast. */
    private function setField(object $obj, string $prop, mixed $value): void
    {
        $rp = new \ReflectionProperty($obj, $prop);
        $rp->setValue($obj, $this->cast($rp->getType(), $value));
    }

    /**
     * Rebuild a readonly WidgetSpec with one field changed. Reflection cannot
     * write an already-initialized readonly property, so we construct a fresh
     * instance: copy every promoted constructor value and override $name.
     */
    private function rebuiltSpec(WidgetSpec $spec, string $name, mixed $value): WidgetSpec
    {
        $rc = new \ReflectionClass($spec);
        $args = [];
        foreach ($rc->getConstructor()->getParameters() as $p) {
            $pn = $p->getName();
            if ($pn === $name) {
                $args[] = $value;
            } elseif (property_exists($spec, $pn)) {
                $args[] = $spec->{$pn};
            } else {
                $args[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
            }
        }

        return $rc->newInstance(...$args);
    }

    private function castField(object $obj, string $prop, mixed $value): mixed
    {
        return $this->cast((new \ReflectionProperty($obj, $prop))->getType(), $value);
    }

    private function fieldType(object $obj, string $prop): ?\ReflectionType
    {
        return (new \ReflectionProperty($obj, $prop))->getType();
    }

    private function cast(?\ReflectionType $type, mixed $value): mixed
    {
        if ($type === null) {
            return $value;
        }
        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $t) {
                if (! $t->allowsNull() || $value === null) {
                    return $this->cast($t, $value);
                }
            }

            return $value;
        }
        $name = $type->getName();

        return match ($name) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL),
            'string' => (string) $value,
            default => $value,
        };
    }

    private function typeDefault(?\ReflectionType $type): mixed
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                if ($t->allowsNull()) {
                    return null;
                }
            }
            $type = $type->getTypes()[0];
        }
        if ($type instanceof \ReflectionIntersectionType) {
            $type = $type->getTypes()[0];
        }
        $name = $type->getName();

        return match ($name) {
            'int' => 0,
            'float' => 0.0,
            'bool' => false,
            'string' => '',
            default => null,
        };
    }

    private function makeSpec(string $type): WidgetSpec
    {
        return match ($type) {
            'button' => new ButtonSpec(label: '按钮'),
            'label' => new LabelSpec(text: '标签'),
            'card' => new CardSpec(),
            'checkbox' => new CheckboxSpec(label: '选项'),
            'input' => new InputSpec(placeholder: '输入…'),
            'progress' => new ProgressSpec(value: 0.5),
            'badge' => new BadgeSpec(text: '标记'),
            'button_group' => new ButtonGroupSpec(),
            default => new ButtonSpec(label: $type),
        };
    }
}

<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Compiler;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrollViewSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\WidgetSpec;

/**
 * Compile .native XML markup into a LayoutNode tree.
 *
 * DSL syntax: a subset of the project's widget specs expressed as XML.
 *
 * **Containers:** `<Row>`, `<Column>` — children are parsed recursively.
 * **ScrollView:** `<ScrollView>` — wraps children in a content column clipped
 *   to a viewport with a ScrollViewSpec.
 * **Widgets:** `<Button>`, `<Label>`, `<Checkbox>`, `<Slider>`, `<TextField>`,
 *   `<TextArea>`, `<SearchField>`, `<Progress>`, `<Radio>`, `<Select>`,
 *   `<BreadcrumbItem>`, `<PaginationItem>`, `<Tab>`, `<Panel>`, `<Card>`,
 *   `<DialogCard>`, `<DialogBody>`, `<ListRow>`, `<TableRow>`.
 *
 * Attribute names map 1:1 to the corresponding *Spec constructor parameter
 * names. Values are auto-coerced (numeric → float, "true"/"false" → bool).
 *
 * Not supported: `<Image>` (pixels are runtime data, not markup).
 *
 * ```xml
 * <?xml version="1.0" encoding="UTF-8"?>
 * <Column gap="16" padding="20" align="center" width="400">
 *   <Label text="Hello" size="24" align="center" />
 *   <Button id="btn-ok" label="OK" variant="filled" width="120" radius="8" />
 * </Column>
 * ```
 */
class NativeLoader
{
    /** @var array<string, class-string<WidgetSpec>> */
    private const ELEMENT_MAP = [
        'Button'         => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ButtonSpec',
        'Label'          => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\LabelSpec',
        'Checkbox'       => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\CheckboxSpec',
        'Slider'         => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\SliderSpec',
        'TextField'      => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\TextFieldSpec',
        'Input'          => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\InputSpec',
        'TextArea'       => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\TextAreaSpec',
        'SearchField'    => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\SearchFieldSpec',
        'Progress'       => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ProgressSpec',
        'Radio'          => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\RadioSpec',
        'Select'         => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\SelectSpec',
        'BreadcrumbItem' => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\BreadcrumbItemSpec',
        'PaginationItem' => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\PaginationItemSpec',
        'Tab'            => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\TabSpec',
        'Panel'          => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\PanelSpec',
        'Card'           => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\CardSpec',
        'DialogCard'     => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\DialogCardSpec',
        'DialogBody'     => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\DialogBodySpec',
        'ListRow'        => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ListRowSpec',
        'TableRow'       => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\TableRowSpec',
        'Badge'          => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\BadgeSpec',
        'Separator'      => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\SeparatorSpec',
        'Icon'           => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\IconSpec',
        'Stack'          => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\StackSpec',
        'Grid'           => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\GridSpec',
        'ButtonGroup'    => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ButtonGroupSpec',
        'RadioGroup'     => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\RadioGroupSpec',
        'ToggleGroup'    => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ToggleGroupSpec',
        'ToggleButton'   => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ToggleButtonSpec',
        'Tooltip'        => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\TooltipSpec',
        'MenuItem'       => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\MenuItemSpec',
        'StatusBar'      => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\StatusBarSpec',
        'Switch'         => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\SwitchSpec',
        'Skeleton'       => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\SkeletonSpec',
        'Spinner'        => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\SpinnerSpec',
        'Split'          => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\SplitSpec',
        'Alert'          => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\AlertSpec',
        'Accordion'      => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\AccordionSpec',
        'TableCell'      => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\TableCellSpec',
        'Resizable'      => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ResizableSpec',
        'Image'            => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ImageSpec',
        'Scrim'            => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ScrimSpec',
        'Bubble'           => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\BubbleSpec',
        'ContextMenu'      => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ContextMenuSpec',
        'Markdown'         => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\MarkdownSpec',
        'Stepper'          => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\StepperSpec',
        'Step'             => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\StepSpec',
        'Timeline'         => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\TimelineSpec',
        'TimelineItem'     => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\TimelineItemSpec',
        'InputGroup'       => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\InputGroupSpec',
        'InputGroupActions' => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\InputGroupActionsSpec',
        'Span'             => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\SpanSpec',
        'Reactions'        => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ReactionsSpec',
        'Chart'            => 'Yangweijie\\Ui2\\Rendering\\WidgetRenderer\\ChartSpec',
    ];

    /** Row-based layouts — children laid out horizontally. */
    private const ROW_CONTAINERS = ['Row', 'ButtonGroup', 'RadioGroup', 'ToggleGroup'];

    /** Column-based layouts — children laid out vertically. */
    private const COLUMN_CONTAINERS = ['Column', 'Stack', 'Grid', 'Split', 'Accordion', 'Resizable', 'Stepper', 'Timeline'];

    /** All structural (non-spec) containers. */
    private const CONTAINER_ELEMENTS = [...self::ROW_CONTAINERS, ...self::COLUMN_CONTAINERS];

    /**
     * Spec elements that also act as layout containers.
     * These create a Column node with the spec painted as background,
     * allowing child elements to render inside the visual box.
     */
    private const SPEC_CONTAINER_ELEMENTS = [
        'Card',
        'Panel',
        'DialogCard',
        'DialogBody',
        'Alert',
        'Bubble',
        'ContextMenu',
    ];

    /**
     * Load a .native file and compile it into a LayoutNode tree.
     *
     * @param  string       $path  Absolute or relative path to the .native file.
     * @throws NativeException     On file-not-found, XML parse error, or
     *                             unknown element.
     */
    public static function load(string $path): LayoutNode
    {
        if (!is_file($path)) {
            throw new NativeException("Native file not found: {$path}");
        }

        $xml = file_get_contents($path);
        if ($xml === false) {
            throw new NativeException("Failed to read native file: {$path}");
        }

        return self::parse($xml);
    }

    /**
     * Parse a .native XML string into a LayoutNode tree.
     *
     * @param  string       $xml  Well-formed .native markup.
     * @throws NativeException    On parse errors.
     */
    public static function parse(string $xml): LayoutNode
    {
        \libxml_use_internal_errors(true);

        $sxe = \simplexml_load_string($xml);
        if ($sxe === false) {
            $errors = \libxml_get_errors();
            \libxml_clear_errors();

            $msg = \implode('; ', \array_map(
                fn (\LibXMLError $e) => \trim($e->message),
                $errors,
            ));

            throw new NativeException("XML parse error: {$msg}");
        }

        return self::parseElement($sxe);
    }

    // ── recursive compiler ──────────────────────────────────────────────────

    private static function parseElement(\SimpleXMLElement $el): LayoutNode
    {
        $name = $el->getName();
        $attrs = self::attrsToArray($el);

        if (\in_array($name, self::CONTAINER_ELEMENTS, true)) {
            return self::createContainer($name, $attrs, $el);
        }

        if ($name === 'ScrollView') {
            return self::createScrollView($attrs, $el);
        }

        if (\in_array($name, self::SPEC_CONTAINER_ELEMENTS, true)) {
            return self::createSpecContainer($name, $attrs, $el);
        }

        return self::createWidget($name, $attrs);
    }

    /**
     * Create a spec container — a Column node whose spec paints a background
     * (Card/Panel/DialogCard/DialogBody), with child elements inside it.
     */
    private static function createSpecContainer(
        string             $name,
        array              $attrs,
        \SimpleXMLElement  $el,
    ): LayoutNode {
        if (!isset(self::ELEMENT_MAP[$name])) {
            throw new NativeException("Unknown element: <{$name}>");
        }

        $class = self::ELEMENT_MAP[$name];
        $id    = $attrs['id'] ?? null;
        $spec  = self::buildSpec($class, $attrs);

        // Use container-specific attrs or fall backs from the spec
        $gap     = self::coerceFloat($attrs['gap'] ?? 0.0);
        $padding = self::coerceFloat($attrs['padding'] ?? 0.0);
        $justify = self::coerceString($attrs['justify'] ?? LayoutStyle::JUSTIFY_START);
        $align   = self::coerceString($attrs['align'] ?? LayoutStyle::ALIGN_STRETCH);
        $width   = \array_key_exists('width', $attrs) ? self::coerceFloat($attrs['width']) : null;
        $height  = \array_key_exists('height', $attrs) ? self::coerceFloat($attrs['height']) : null;

        $node = LayoutNode::column(
            gap: $gap,
            padding: $padding,
            justify: $justify,
            align: $align,
            id: $id,
            width: $width,
            height: $height,
        );
        $node->spec = $spec;

        foreach ($el->children() as $child) {
            $node->child(self::parseElement($child));
        }

        return $node;
    }

    private static function createContainer(
        string             $name,
        array              $attrs,
        \SimpleXMLElement  $el,
    ): LayoutNode {
        $id      = $attrs['id'] ?? null;
        $gap     = self::coerceFloat($attrs['gap'] ?? 0.0);
        $padding = self::coerceFloat($attrs['padding'] ?? 0.0);
        $justify = self::coerceString($attrs['justify'] ?? LayoutStyle::JUSTIFY_START);
        $align   = self::coerceString($attrs['align'] ?? LayoutStyle::ALIGN_STRETCH);
        $width   = \array_key_exists('width', $attrs) ? self::coerceFloat($attrs['width']) : null;
        $height  = \array_key_exists('height', $attrs) ? self::coerceFloat($attrs['height']) : null;

        $node = \in_array($name, self::ROW_CONTAINERS, true)
            ? LayoutNode::row(gap: $gap, padding: $padding, justify: $justify, align: $align, id: $id, width: $width, height: $height)
            : LayoutNode::column(gap: $gap, padding: $padding, justify: $justify, align: $align, id: $id, width: $width, height: $height);

        // Attach structural spec for containers that have one (Stack, Grid, Split, etc.)
        if (isset(self::ELEMENT_MAP[$name])) {
            $node->spec = self::buildSpec(self::ELEMENT_MAP[$name], $attrs);
        }

        foreach ($el->children() as $child) {
            $node->child(self::parseElement($child));
        }

        return $node;
    }

    private static function createWidget(string $name, array $attrs): LayoutNode
    {
        if (!isset(self::ELEMENT_MAP[$name])) {
            throw new NativeException("Unknown element: <{$name}>");
        }

        $class  = self::ELEMENT_MAP[$name];
        $id     = $attrs['id'] ?? null;
        $width  = \array_key_exists('width', $attrs) ? self::coerceFloat($attrs['width']) : null;
        $height = \array_key_exists('height', $attrs) ? self::coerceFloat($attrs['height']) : null;

        $spec = self::buildSpec($class, $attrs);

        return LayoutNode::leaf($id, $spec, $width, $height);
    }

    /**
     * Build a ScrollView — viewport row (ScrollViewSpec) + content column.
     *
     * The viewport node's spec is a ScrollViewSpec. Its single child is the
     * content column, which holds the parsed children elements.
     */
    private static function createScrollView(
        array              $attrs,
        \SimpleXMLElement  $el,
    ): LayoutNode {
        $id         = $attrs['id'] ?? null;
        $width      = self::coerceFloat($attrs['width'] ?? 200.0);
        $height     = self::coerceFloat($attrs['height'] ?? 300.0);
        $radius     = self::coerceFloat($attrs['radius'] ?? 8.0);
        $vertical   = \array_key_exists('vertical', $attrs)
            ? self::coerceBool($attrs['vertical']) : true;
        $horizontal = \array_key_exists('horizontal', $attrs)
            ? self::coerceBool($attrs['horizontal']) : false;
        $gap        = self::coerceFloat($attrs['gap'] ?? 0.0);
        $padding    = self::coerceFloat($attrs['padding'] ?? 0.0);

        // Children parsed from the content
        $children = [];
        foreach ($el->children() as $child) {
            $children[] = self::parseElement($child);
        }

        // Initial content dimensions (FlexLayout will recalculate)
        $gutter       = 12.0;       // matches ScrollViewRenderer
        $contentW     = \max(0.0, $width - $gutter);
        $contentH     = $height;    // placeholder

        $spec = new ScrollViewSpec(
            contentWidth: $contentW,
            contentHeight: $contentH,
            viewportWidth: $width,
            viewportHeight: $height,
            radius: $radius,
            vertical: $vertical,
            horizontal: $horizontal,
        );

        $content = LayoutNode::column(
            gap: $gap,
            padding: $padding,
            align: LayoutStyle::ALIGN_START,
        );
        $content->style->width = $contentW;

        foreach ($children as $child) {
            $content->child($child);
        }

        $viewport = LayoutNode::row(
            id: $id,
            justify: LayoutStyle::JUSTIFY_START,
            align: LayoutStyle::ALIGN_START,
        );
        $viewport->style->width  = $width;
        $viewport->style->height = $height;
        $viewport->spec          = $spec;
        $viewport->child($content);

        return $viewport;
    }

    // ── attribute / type helpers ────────────────────────────────────────────

    /** @return array<string, string> */
    private static function attrsToArray(\SimpleXMLElement $el): array
    {
        $attrs = [];
        foreach ($el->attributes() as $name => $value) {
            $attrs[(string) $name] = (string) $value;
        }
        return $attrs;
    }

    /**
     * Build a WidgetSpec instance from element attributes.
     *
     * Uses reflection to map XML attribute values to constructor parameters
     * in the correct order, applying type coercion for bool/int/float.
     */
    private static function buildSpec(string $class, array $attrs): WidgetSpec
    {
        $ref  = new \ReflectionClass($class);
        $ctor = $ref->getConstructor();

        if ($ctor === null) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $name  = $param->getName();

            if (\array_key_exists($name, $attrs)) {
                $args[] = self::coerceValue($attrs[$name], $param->getType());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new NativeException(
                    "Missing required parameter '{$name}' for {$class}"
                );
            }
        }

        /** @var WidgetSpec */
        return $ref->newInstanceArgs($args);
    }

    private static function coerceValue(mixed $value, ?\ReflectionType $type): mixed
    {
        if ($type === null) {
            return $value;
        }

        if ($type instanceof \ReflectionNamedType) {
            return match ($type->getName()) {
                'bool'   => self::coerceBool($value),
                'int'    => self::coerceInt($value),
                'float'  => self::coerceFloat($value),
                'string' => self::coerceString($value),
                default  => $value,
            };
        }

        return $value;
    }

    private static function coerceBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_string($value)) {
            return $value === 'true' || $value === '1' || $value === 'yes';
        }
        return (bool) $value;
    }

    private static function coerceInt(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value)) {
            return (int) $value;
        }
        return (int) $value;
    }

    private static function coerceFloat(mixed $value): float
    {
        if (\is_float($value)) {
            return $value;
        }
        if (\is_string($value)) {
            return (float) $value;
        }
        return (float) $value;
    }

    private static function coerceString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        return (string) $value;
    }
}

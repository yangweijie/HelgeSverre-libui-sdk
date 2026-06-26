<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

/**
 * Native right-click context menu — cross-platform (macOS/Linux/Windows).
 *
 * Shows a native popup context menu at the current cursor position.
 * Best used inside an **Area** widget's mouse handler — detect right-click
 * via `$event->isRightButtonDown()` and call `$this->show()`.
 *
 * ```php
 * // 1. Build the menu
 * $menu = new ContextMenu();
 * $menu->addItem('Open', fn() => openFile())
 *      ->addSeparator()
 *      ->addItem('Delete', fn() => del(), disabled: true)
 *      ->addItem('Info', fn() => showInfo(), checked: true);
 *
 * // 2. Wire to an Area delegate's mouse handler
 * class MyArea extends AreaDelegate
 * {
 *     public function __construct(private ContextMenu $menu) {}
 *     public function mouse(AreaMouseEvent $event): void
 *     {
 *         if ($event->isRightButtonDown()) {
 *             $this->menu->show(); // shows at cursor position
 *         }
 *     }
 * }
 * ```
 */
class ContextMenu
{
    /** @var list<array{text:string, callback:?callable, disabled:bool, checked:bool}> */
    private array $items = [];

    /** The widget that triggered this context menu (for callback context). */
    private mixed $source = null;

    private static ?\FFI $ffi = null;

    /**
     * Add a menu item.
     *
     * @param string   $text     Item label. '-' creates a separator.
     * @param callable $callback Called when item is selected.
     *                           Signature: `fn(?ContextMenu $menu): void`
     *                           Receives the ContextMenu instance so it can
     *                           access the source widget via `$menu->getSource()`.
     * @param bool     $disabled Grey out the item?
     * @param bool     $checked  Show a checkmark?
     * @return $this
     */
    public function addItem(
        string $text,
        ?callable $callback = null,
        bool $disabled = false,
        bool $checked = false,
    ): static {
        $this->items[] = [
            'text'     => $text,
            'callback' => $callback,
            'disabled' => $disabled,
            'checked'  => $checked,
        ];
        return $this;
    }

    /**
     * Add a separator line.
     */
    public function addSeparator(): static
    {
        return $this->addItem('-');
    }

    /**
     * Set the source widget that triggered this context menu.
     *
     * Callbacks can retrieve it via `$menu->getSource()`.
     *
     * @return $this
     */
    public function setSource(mixed $source): static
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Get the source widget that triggered this context menu.
     */
    public function getSource(): mixed
    {
        return $this->source;
    }

    /**
     * Show the context menu at the current mouse cursor position.
     *
     * Blocks until the user selects an item or dismisses the menu.
     * The native bridge handles cursor position on all platforms.
     * Callbacks receive `$this` so they can access `getSource()`.
     *
     * @return int Index of selected item, or -1 if cancelled/none selected.
     */
    public function show(): int
    {
        $ffi = self::ffi();

        // Build JSON menu description
        $jsonItems = [];
        foreach ($this->items as $item) {
            $entry = ['text' => $item['text']];
            if ($item['disabled']) $entry['disabled'] = true;
            if ($item['checked'])  $entry['checked'] = true;
            $jsonItems[] = $entry;
        }

        $menuJson = \json_encode($jsonItems, \JSON_UNESCAPED_UNICODE);
        if ($menuJson === false) {
            return -1;
        }

        $selected = $ffi->cm_show_menu($menuJson, 0.0, 0.0);

        // Call the callback for the selected item
        if ($selected >= 0 && $selected < \count($this->items) && $this->items[$selected]['callback'] !== null) {
            ($this->items[$selected]['callback'])($this);
        }

        return $selected;
    }

    /**
     * Alias for show() — shown at current cursor.
     *
     * Use this inside an AreaDelegate::mouse() handler after detecting
     * a right-click:
     *
     *     public function mouse(AreaMouseEvent $event): void
     *     {
     *         if ($event->isRightButtonDown()) {
     *             $this->menu->showAtCursor();
     *         }
     *     }
     */
    public function showAtCursor(): int
    {
        return $this->show();
    }

    /**
     * Load the platform-specific context menu bridge FFI.
     */
    private static function ffi(): \FFI
    {
        if (self::$ffi !== null) {
            return self::$ffi;
        }

        $libPath = self::libraryPath();

        if (!\file_exists($libPath)) {
            throw new \RuntimeException(
                'Context menu bridge not found at: ' . $libPath . \PHP_EOL
                . self::compileInstructions(),
            );
        }

        self::$ffi = \FFI::cdef(
            'int cm_show_menu(const char *menu_json, double x, double y);',
            $libPath,
        );

        return self::$ffi;
    }

    /**
     * Resolve the platform-specific bridge library path.
     */
    private static function libraryPath(): string
    {
        $base = \dirname(__DIR__, 2) . '/bridge';

        return match (\PHP_OS_FAMILY) {
            'Darwin'  => $base . '/context_menu.dylib',
            'Linux'   => $base . '/libcontext_menu.so',
            'Windows' => $base . '/context_menu.dll',
            default   => throw new \RuntimeException('Unsupported OS: ' . \PHP_OS_FAMILY),
        };
    }

    /**
     * Get compilation instructions for the platform.
     */
    private static function compileInstructions(): string
    {
        return match (\PHP_OS_FAMILY) {
            'Darwin'  => 'Compile: cd bridge && clang -shared -fobjc-arc context_menu.m -framework Foundation -framework AppKit -o context_menu.dylib',
            'Linux'   => 'Compile: cd bridge && gcc -shared -fPIC context_menu_linux.c $(pkg-config --cflags --libs gtk+-3.0) -ljansson -o libcontext_menu.so',
            'Windows' => 'Compile: cd bridge && cl /LD context_menu_win.c /Fe:context_menu.dll user32.lib',
            default   => 'See bridge/README.md for compile instructions.',
        };
    }
}

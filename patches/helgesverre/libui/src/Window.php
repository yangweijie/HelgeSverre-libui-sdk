<?php

declare(strict_types=1);

namespace Libui;

/**
 * Top-level window. Adds lifecycle sugar on top of the generated API: sensible
 * constructor defaults, an onClose() cleanup hook, and a one-call run().
 *
 * PATCHED: tracks the title of the first Window created, so MenuOrderException
 * can report which window locked the menu system.
 */
class Window extends Generated\Window
{
    /** True once any Window has been constructed this process — menus are then locked. */
    private static bool $menusLocked = false;

    /** The title of whichever Window was constructed first (used by MenuOrderException). */
    private static ?string $firstWindowTitle = null;

    /** @var (callable():void)|null */
    private $onClose = null;

    /** Content size requested at construction; the fallback for {@see getContentSize()}. */
    private int $width = 640;

    private int $height = 480;

    public function __construct(string $title, int $width = 640, int $height = 480, bool $hasMenubar = false)
    {
        parent::__construct($title, $width, $height, $hasMenubar);
        $this->width = $width;
        $this->height = $height;

        if (!self::$menusLocked) {
            self::$firstWindowTitle = $title;
        }
        self::$menusLocked = true; // libui freezes the menu list at first window creation
    }

    /** Whether any Window has been created (after which new Menus are illegal). */
    public static function menusLocked(): bool
    {
        return self::$menusLocked;
    }

    /**
     * The title of the first Window that was created in this process, or null
     * if no Window has been constructed yet.
     */
    public static function firstWindowTitle(): ?string
    {
        return self::$firstWindowTitle;
    }

    /**
     * Reset the menu-ordering lock so a fresh libui session (after Ffi::uninit())
     * may build menus again. Called automatically by Ffi::uninit(); also useful
     * directly in tests that need to construct a Menu after a Window already exists.
     *
     * @internal
     */
    public static function resetMenuLock(): void
    {
        self::$menusLocked = false;
        self::$firstWindowTitle = null;
    }

    /** A Dialogs facade bound to this window as the parent. */
    public function dialogs(): Dialogs
    {
        return new Dialogs($this);
    }

    /**
     * The window's current content size, falling back to the constructed size
     * when libui reports a non-positive value (it may on Unix before layout).
     *
     * The content size excludes window decorations like menus or title bars.
     *
     * @return array{int, int} [width, height]
     */
    public function getContentSize(): array
    {
        $out = Ffi::get()->new('int[2]');
        Ffi::get()->uiWindowContentSize($this->handle, \FFI::addr($out[0]), \FFI::addr($out[1]));

        return [
            $out[0] > 0 ? $out[0] : $this->width,
            $out[1] > 0 ? $out[1] : $this->height,
        ];
    }

    /**
     * The window's current position, measured from the top-left of the screen.
     *
     * @note May return inaccurate or dummy values on Unix platforms.
     *
     * @return array{int, int} [x, y]
     */
    public function getPosition(): array
    {
        $out = Ffi::get()->new('int[2]');
        Ffi::get()->uiWindowPosition($this->handle, \FFI::addr($out[0]), \FFI::addr($out[1]));

        return [$out[0], $out[1]];
    }

    /**
     * Best-effort primary-display size in pixels, cached for the process.
     *
     * macOS: CoreGraphics FFI (pure C, ships with OS — no extra toolchain).
     * Linux: xrandr → xdotool → xdpyinfo (first match wins).
     * Windows: PowerShell System.Windows.Forms → wmic.
     *
     * Returns null if no method succeeds, leaving the caller to supply
     * dimensions explicitly.
     *
     * @return array{int, int}|null [width, height], or null if unavailable.
     */
    private static function detectScreenSize(): ?array
    {
        /** @var array{int, int}|null $size */
        static $size = null;
        static $probed = false;

        if ($probed) {
            return $size;
        }
        $probed = true;

        return $size = match (\PHP_OS_FAMILY) {
            'Darwin' => self::screenSizeDarwin(),
            'Linux'  => self::screenSizeLinux(),
            'Windows' => self::screenSizeWindows(),
            default  => null,
        };
    }

    /** macOS: CoreGraphics FFI — primary display bounds. */
    private static function screenSizeDarwin(): ?array
    {
        try {
            $cg = \FFI::cdef(
                'typedef uint32_t CGDirectDisplayID;'
                . 'typedef struct { double x; double y; } CGPoint;'
                . 'typedef struct { double width; double height; } CGSize;'
                . 'typedef struct { CGPoint origin; CGSize size; } CGRect;'
                . 'CGDirectDisplayID CGMainDisplayID(void);'
                . 'CGRect CGDisplayBounds(CGDirectDisplayID display);',
                '/System/Library/Frameworks/CoreGraphics.framework/CoreGraphics',
            );
            // @phpstan-ignore-next-line dynamic CoreGraphics FFI calls on a local \FFI handle
            $bounds = $cg->CGDisplayBounds($cg->CGMainDisplayID());

            return [(int) $bounds->size->width, (int) $bounds->size->height];
        } catch (\Throwable) {
            return null;
        }
    }

    /** Linux: xrandr → xdotool → xdpyinfo. */
    private static function screenSizeLinux(): ?array
    {
        // 1. xrandr query — most reliable for X11
        $out = \shell_exec('xrandr --query 2>/dev/null');
        if (\is_string($out) && $out !== '') {
            // Match primary display first, then any connected display with '*'
            if (\preg_match('/primary\s+\d+x\d+\s+\+?\d+\+?\d+\s+.*?(\d+)x(\d+)/', $out, $m)
                || \preg_match('/(\d+)x(\d+).*\*/', $out, $m)) {
                return [(int) $m[1], (int) $m[2]];
            }
        }

        // 2. xdotool (common on modern desktops)
        $out = \shell_exec('xdotool getdisplaygeometry 2>/dev/null');
        if (\is_string($out) && $out !== '' && \preg_match('/(\d+)\s+(\d+)/', $out, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        // 3. xdpyinfo fallback
        $out = \shell_exec('xdpyinfo 2>/dev/null | grep dimensions');
        if (\is_string($out) && $out !== '' && \preg_match('/(\d+)x(\d+)/', $out, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return null;
    }

    /** Windows: PowerShell System.Windows.Forms → wmic. */
    private static function screenSizeWindows(): ?array
    {
        // 1. PowerShell with WinForms (most reliable)
        $script = 'Add-Type -AssemblyName System.Windows.Forms;'
            . '[System.Windows.Forms.Screen]::PrimaryScreen.Bounds.Size.Width;'
            . '[System.Windows.Forms.Screen]::PrimaryScreen.Bounds.Size.Height';
        $out = \shell_exec("powershell -Command \"{$script}\" 2>NUL");
        if (\is_string($out) && $out !== '') {
            $parts = \array_map('\intval', \array_filter(\explode("\n", $out)));
            if (\count($parts) >= 2 && $parts[0] > 0 && $parts[1] > 0) {
                return [$parts[0], $parts[1]];
            }
        }

        // 2. wmic fallback
        $out = \shell_exec('wmic path Win32_VideoController'
            . ' get CurrentHorizontalResolution,CurrentVerticalResolution 2>NUL');
        if (\is_string($out) && $out !== '' && \preg_match('/(\d+)\s+(\d+)/', $out, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return null;
    }

    // ────── Position presets ──────

    public const CENTER       = 'center';
    public const TOP_LEFT     = 'topLeft';
    public const TOP_CENTER   = 'topCenter';
    public const TOP_RIGHT    = 'topRight';
    public const CENTER_LEFT  = 'centerLeft';
    public const CENTER_RIGHT = 'centerRight';
    public const BOTTOM_LEFT    = 'bottomLeft';
    public const BOTTOM_CENTER  = 'bottomCenter';
    public const BOTTOM_RIGHT   = 'bottomRight';

    /** @var array<string, \Closure(int,int,int,int):array{int,int}> */
    private static array $positionCalculators;

    /**
     * Position the window at a named screen region.
     *
     * Available presets (use the class constants):
     *   CENTER, TOP_LEFT, TOP_CENTER, TOP_RIGHT,
     *   CENTER_LEFT, CENTER_RIGHT,
     *   BOTTOM_LEFT, BOTTOM_CENTER, BOTTOM_RIGHT
     *
     * Screen dimensions are auto-detected on all platforms when omitted;
     * pass them explicitly to target a specific display.
     *
     * @see centered()  Convenience wrapper for CENTER.
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function positionOnScreen(
        string $position,
        ?int   $screenWidth = null,
        ?int   $screenHeight = null,
    ): static {
        if ($screenWidth === null || $screenHeight === null) {
            $screen = self::detectScreenSize();
            if ($screen === null) {
                throw new \RuntimeException(
                    'Window::positionOnScreen() cannot detect the screen size on this platform.'
                    . ' Pass explicit dimensions, e.g. $window->positionOnScreen(Window::CENTER, 1920, 1080).',
                );
            }
            [$screenWidth, $screenHeight] = $screen;
        }

        [$winWidth, $winHeight] = $this->getContentSize();

        $calc = self::positionCalc($position);
        [$x, $y] = $calc($screenWidth, $screenHeight, $winWidth, $winHeight);

        return $this->setPosition(
            (int) \max(0, $x),
            (int) \max(0, $y),
        );
    }

    /** Centres on screen. Alias for positionOnScreen(CENTER). */
    public function centered(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        return $this->positionOnScreen(self::CENTER, $screenWidth, $screenHeight);
    }

    /**
     * Position this dialog window centred over the given parent window.
     *
     * Uses the parent's position and content size to compute a centred offset.
     * Falls back to screen-centering if the parent's position is (0,0) or the
     * child is larger than the parent.
     */
    public function centeredOn(self $parent): static
    {
        [$pw, $ph] = $parent->getContentSize();
        [$px, $py] = $parent->getPosition();
        [$cw, $ch] = $this->getContentSize();

        $x = $px + (int)(($pw - $cw) / 2);
        $y = $py + (int)(($ph - $ch) / 2);

        return $this->setPosition(\max(0, $x), \max(0, $y));
    }

    /** Top-left corner. */
    public function topLeft(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        return $this->positionOnScreen(self::TOP_LEFT, $screenWidth, $screenHeight);
    }

    /** Top-center (centred horizontally, flush top). */
    public function topCenter(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        return $this->positionOnScreen(self::TOP_CENTER, $screenWidth, $screenHeight);
    }

    /** Top-right corner. */
    public function topRight(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        return $this->positionOnScreen(self::TOP_RIGHT, $screenWidth, $screenHeight);
    }

    /** Vertically centred on the left edge. */
    public function centerLeft(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        return $this->positionOnScreen(self::CENTER_LEFT, $screenWidth, $screenHeight);
    }

    /** Vertically centred on the right edge. */
    public function centerRight(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        return $this->positionOnScreen(self::CENTER_RIGHT, $screenWidth, $screenHeight);
    }

    /** Bottom-left corner. */
    public function bottomLeft(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        return $this->positionOnScreen(self::BOTTOM_LEFT, $screenWidth, $screenHeight);
    }

    /** Bottom-centre (centred horizontally, flush bottom). */
    public function bottomCenter(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        return $this->positionOnScreen(self::BOTTOM_CENTER, $screenWidth, $screenHeight);
    }

    /** Bottom-right corner. */
    public function bottomRight(?int $screenWidth = null, ?int $screenHeight = null): static
    {
        return $this->positionOnScreen(self::BOTTOM_RIGHT, $screenWidth, $screenHeight);
    }

    /**
     * Lazy-initialised map of position name → (sw,sh,ww,wh) → [x,y].
     *
     * @return \Closure(int,int,int,int):array{int,int}
     */
    private static function positionCalc(string $name): \Closure
    {
        self::$positionCalculators ??= [
            self::CENTER       => fn(int $sw, int $sh, int $ww, int $wh): array
                                 => [(int)(($sw - $ww) / 2), (int)(($sh - $wh) / 2)],
            self::TOP_LEFT     => fn(): array => [0, 0],
            self::TOP_CENTER   => fn(int $sw, int $_, int $ww): array
                                 => [(int)(($sw - $ww) / 2), 0],
            self::TOP_RIGHT    => fn(int $sw, int $_, int $ww): array
                                 => [\max(0, $sw - $ww), 0],
            self::CENTER_LEFT  => fn(int $_, int $sh, int $__, int $wh): array
                                 => [0, (int)(($sh - $wh) / 2)],
            self::CENTER_RIGHT => fn(int $sw, int $sh, int $ww, int $wh): array
                                 => [\max(0, $sw - $ww), (int)(($sh - $wh) / 2)],
            self::BOTTOM_LEFT  => fn(int $_, int $sh, int $__, int $wh): array
                                 => [0, \max(0, $sh - $wh)],
            self::BOTTOM_CENTER=> fn(int $sw, int $sh, int $ww, int $wh): array
                                 => [(int)(($sw - $ww) / 2), \max(0, $sh - $wh)],
            self::BOTTOM_RIGHT => fn(int $sw, int $sh, int $ww, int $wh): array
                                 => [\max(0, $sw - $ww), \max(0, $sh - $wh)],
        ];

        if (!isset(self::$positionCalculators[$name])) {
            throw new \InvalidArgumentException(
                "Unknown position preset: {$name}. "
                . 'Use one of: ' . \implode(', ', \array_keys(self::$positionCalculators)),
            );
        }

        return self::$positionCalculators[$name];
    }

    /**
     * Run cleanup when the window is closed, before the app quits. Unlike the raw
     * onClosing(), you don't manage the loop or return a value.
     */
    public function onClose(callable $cb): static
    {
        $this->onClose = $cb;
        return $this;
    }

    /**
     * Show the window and run the event loop until it closes — the all-in-one
     * entry point for a single-window app. Initialises libui if needed, wires the
     * close button to quit (after any onClose() cleanup), and uninits on exit.
     *
     * $afterClose runs once the loop has returned and the window (and its child
     * controls) have been destroyed, just before libui shuts down — the safe
     * place to free native resources that outlive a control, e.g. a TableModel
     * (libui aborts if a model is freed while its table is still alive).
     *
     * For multiple windows or an app-level should-quit handler, use {@see App}.
     */
    public function run(?callable $afterClose = null): void
    {
        Ffi::init();

        $this->onClosing(function () {
            if ($this->onClose !== null) {
                ($this->onClose)();
            }
            Ffi::quit();
            return true;
        });

        $this->show();

        try {
            Ffi::main();

            if ($afterClose !== null) {
                $afterClose();
            }
        } finally {
            // Always tear libui down, even if $afterClose throws — otherwise the
            // next init() in the same process would be left in a bad state.
            Ffi::uninit();
        }
    }
}

<?php

declare(strict_types=1);

/**
 * Tetris — a full-featured Tetris game built with the ui2 PHP GUI SDK.
 *
 * Highlights:
 *   - Area + AreaDelegate for the custom-drawn game board
 *   - AreaDelegate::key() with ExtKey for arrow-key input
 *   - Loop::repeat() for the gravity timer
 *   - Two Areas: game board and next-piece preview
 *   - DrawContext with fillRect, drawString, roundedRect
 *   - Labels updated via setText() for score/level/lines
 *
 * Run: php examples/tetris.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Area;
use Libui\AreaDelegate;
use Libui\Build;
use Libui\Color;
use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Libui\Draw\DrawContext;
use Libui\Draw\Brush;
use Libui\Draw\StrokeParams;
use Libui\Text\FontDescriptor;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaKeyEvent;
use Libui\Generated\Enum\ExtKey;
use Libui\Generated\Enum\TextWeight;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Group;
use Libui\Loop;

// ═════════════════════════════════════════════════════════════════════════════
// CONSTANTS
// ═════════════════════════════════════════════════════════════════════════════

define('COLS', 10);
define('ROWS', 20);
define('CELL', 30);
define('BOARD_W', COLS * CELL);    // 300
define('BOARD_H', ROWS * CELL);    // 600
define('SIDEBAR_W', 200);

/** Tetromino colours (RGB hex) — index 0..6 matches piece type. */
define('PIECE_COLORS', [
    0x00F0F0, // I cyan
    0xF0F000, // O yellow
    0xA000F0, // T purple
    0x00F000, // S green
    0xF00000, // Z red
    0x0000F0, // J blue
    0xF0A000, // L orange
]);

/** Background colour for the game area. */
define('BG_COLOR', 0x0F172A);     // slate-900

// ═════════════════════════════════════════════════════════════════════════════
// SHAPE HELPERS
// ═════════════════════════════════════════════════════════════════════════════

/** Return the shape matrix for a piece type (0..6) in its default rotation. */
function pieceShape(int $type): array
{
    static $shapes = [
        [[0,0,0,0],[1,1,1,1],[0,0,0,0],[0,0,0,0]], // I
        [[1,1],[1,1]],                               // O
        [[0,1,0],[1,1,1],[0,0,0]],                   // T
        [[0,1,1],[1,1,0],[0,0,0]],                   // S
        [[1,1,0],[0,1,1],[0,0,0]],                   // Z
        [[1,0,0],[1,1,1],[0,0,0]],                   // J
        [[0,0,1],[1,1,1],[0,0,0]],                   // L
    ];

    return $shapes[$type];
}

/** Rotate a 2D matrix 90° clockwise. */
function rotateMatrix(array $matrix): array
{
    $h = count($matrix);
    $w = count($matrix[0]);
    $result = array_fill(0, $w, array_fill(0, $h, 0));

    for ($r = 0; $r < $h; ++$r) {
        for ($c = 0; $c < $w; ++$c) {
            $result[$c][$h - 1 - $r] = $matrix[$r][$c];
        }
    }

    return $result;
}

/** Drop interval in ms for a given level. */
function dropInterval(int $level): int
{
    return max(80, 1000 - ($level - 1) * 80);
}

/** Draw a single Tetris cell with 3D bevel effect at (col, row). */
function drawCell(DrawContext $ctx, int $col, int $row, int $hexColor): void
{
    $x = $col * CELL + 1;
    $y = $row * CELL + 1;
    $size = CELL - 2;

    $ctx->fillRect((float) $x, (float) $y, (float) $size, (float) $size,
        Brush::rgb($hexColor));

    // Top-left highlight
    $hl = Brush::color(Color::rgba(1.0, 1.0, 1.0, 0.35));
    $ctx->fillRect((float) ($x - 1), (float) ($y - 1), (float) ($size + 2), 2.0, $hl);
    $ctx->fillRect((float) ($x - 1), (float) ($y - 1), 2.0, (float) ($size + 2), $hl);

    // Bottom-right shadow
    $sh = Brush::color(Color::rgba(0.0, 0.0, 0.0, 0.35));
    $ctx->fillRect((float) ($x - 1), (float) ($y + $size - 1), (float) ($size + 2), 2.0, $sh);
    $ctx->fillRect((float) ($x + $size - 1), (float) ($y - 1), 2.0, (float) ($size + 2), $sh);
}

// ═════════════════════════════════════════════════════════════════════════════
// GAME STATE (mutable — shared between Areas, delegates, and game-logic closures)
// ═════════════════════════════════════════════════════════════════════════════

$state = new class {
    public array $board;           // int[ROWS][COLS] — -1 = empty, 0..6 = piece colour index
    public int   $currentType = 0;
    public array $currentShape = [];
    public int   $currentX = 3;
    public int   $currentY = 0;
    public int   $nextType = 0;
    public int   $score = 0;
    public int   $level = 1;
    public int   $totalLines = 0;
    public bool  $gameOver = false;
    public bool  $paused = false;
    public ?int  $timerId = null;

    // Injected after UI construction (chicken-and-egg with Area constructor)
    public ?Area  $gameArea = null;
    public ?Area  $previewArea = null;
    public ?Label $scoreLabel = null;
    public ?Label $levelLabel = null;
    public ?Label $linesLabel = null;

    public function __construct()
    {
        $this->board = array_fill(0, ROWS, array_fill(0, COLS, -1));
    }
};

// ═════════════════════════════════════════════════════════════════════════════
// GAME-LOGIC CLOSURES
// ═════════════════════════════════════════════════════════════════════════════

/** Check whether `$shape` placed at ($offX, $offY) is valid (no collision, in bounds). */
$isValid = function (array $shape, int $offX, int $offY) use ($state): bool {
    foreach ($shape as $r => $row) {
        foreach ($row as $c => $cell) {
            if ($cell === 0) {
                continue;
            }
            $x = $offX + $c;
            $y = $offY + $r;
            if ($x < 0 || $x >= COLS || $y >= ROWS) {
                return false;
            }
            if ($y >= 0 && $state->board[$y][$x] !== -1) {
                return false;
            }
        }
    }
    return true;
};

/**
 * Lock the current piece into the board, clear completed lines, update score,
 * then spawn the next piece. Sets gameOver if the new piece collides immediately.
 */
$lockPiece = function () use ($state, &$isValid): void {
    $shape = $state->currentShape;
    $type = $state->currentType;

    foreach ($shape as $r => $row) {
        foreach ($row as $c => $cell) {
            if ($cell === 0) {
                continue;
            }
            $x = $state->currentX + $c;
            $y = $state->currentY + $r;
            if ($y >= 0 && $y < ROWS && $x >= 0 && $x < COLS) {
                $state->board[$y][$x] = $type;
            }
        }
    }

    // Clear completed lines
    $cleared = 0;
    for ($y = ROWS - 1; $y >= 0; --$y) {
        $full = true;
        for ($x = 0; $x < COLS; ++$x) {
            if ($state->board[$y][$x] === -1) {
                $full = false;
                break;
            }
        }
        if ($full) {
            array_splice($state->board, $y, 1);
            array_unshift($state->board, array_fill(0, COLS, -1));
            ++$cleared;
            ++$y; // stay on this row index to re-check
        }
    }

    // Score
    if ($cleared > 0) {
        $points = [0, 100, 300, 500, 800];
        $state->score += ($points[$cleared] ?? 800) * $state->level;
        $state->totalLines += $cleared;
        $state->level = (int) floor($state->totalLines / 10) + 1;
    }

    $state->scoreLabel?->setText("Score: {$state->score}");
    $state->levelLabel?->setText("Level: {$state->level}");
    $state->linesLabel?->setText("Lines: {$state->totalLines}");

    // Spawn next piece
    $state->currentType = $state->nextType;
    $state->currentShape = pieceShape($state->currentType);
    $state->currentX = (int) ((COLS - count($state->currentShape[0])) / 2);
    $state->currentY = 0;
    $state->nextType = random_int(0, 6);

    if (!$isValid($state->currentShape, $state->currentX, $state->currentY)) {
        $state->gameOver = true;
        if ($state->timerId !== null) {
            Loop::cancel($state->timerId);
            $state->timerId = null;
        }
    }
};

/**
 * Gravity tick — called by Loop::repeat(). Moves the current piece down one row
 * (or locks it if blocked). Always returns true to keep the timer alive; the
 * tick function itself handles stopping via gameOver.
 */
$tick = null; // forward-declare for self-reference
$tick = function () use ($state, &$isValid, &$lockPiece, &$tick): bool {
    if ($state->gameOver) {
        return false;
    }
    if ($state->paused) {
        return true;
    }

    if ($isValid($state->currentShape, $state->currentX, $state->currentY + 1)) {
        ++$state->currentY;
    } else {
        $lockPiece();
    }

    $state->gameArea?->queueRedrawAll();
    $state->previewArea?->queueRedrawAll();

    // Restart timer if the level changed (or just continue)
    if (!$state->gameOver) {
        Loop::cancel($state->timerId);
        $state->timerId = Loop::repeat(dropInterval($state->level), $tick);
    }

    return true;
};

/** Hard drop — drop the current piece to the bottom instantly. */
$hardDrop = function () use ($state, &$isValid, &$lockPiece): void {
    if ($state->gameOver || $state->paused) {
        return;
    }

    $rows = 0;
    while ($isValid($state->currentShape, $state->currentX, $state->currentY + 1)) {
        ++$state->currentY;
        ++$rows;
    }
    $state->score += $rows * 2;
    $state->scoreLabel?->setText("Score: {$state->score}");

    $lockPiece();
    $state->gameArea?->queueRedrawAll();
    $state->previewArea?->queueRedrawAll();
};

/** Restart the game from scratch. */
$restart = function () use ($state, &$tick, &$lockPiece): void {
    if ($state->timerId !== null) {
        Loop::cancel($state->timerId);
        $state->timerId = null;
    }

    $state->board = array_fill(0, ROWS, array_fill(0, COLS, -1));
    $state->score = 0;
    $state->level = 1;
    $state->totalLines = 0;
    $state->gameOver = false;
    $state->paused = false;

    // Spawn initial pieces
    $state->nextType = random_int(0, 6);
    $state->currentType = random_int(0, 6);
    $state->currentShape = pieceShape($state->currentType);
    $state->currentX = (int) ((COLS - count($state->currentShape[0])) / 2);
    $state->currentY = 0;
    $state->nextType = random_int(0, 6);

    $state->scoreLabel?->setText('Score: 0');
    $state->levelLabel?->setText('Level: 1');
    $state->linesLabel?->setText('Lines: 0');

    $state->timerId = Loop::repeat(dropInterval(1), $tick);

    $state->gameArea?->queueRedrawAll();
    $state->previewArea?->queueRedrawAll();
};

// ═════════════════════════════════════════════════════════════════════════════
// UI — Labels
// ═════════════════════════════════════════════════════════════════════════════

Ffi::init();

$scoreLabel = new Label('Score: 0');
$levelLabel = new Label('Level: 1');
$linesLabel = new Label('Lines: 0');
$nextLabel  = new Label('Next:');

$state->scoreLabel = $scoreLabel;
$state->levelLabel = $levelLabel;
$state->linesLabel = $linesLabel;

// ═════════════════════════════════════════════════════════════════════════════
// UI — Game-Board Area Delegate
// ═════════════════════════════════════════════════════════════════════════════

$gameDelegate = new class ($state) extends AreaDelegate {
    public function __construct(
        private readonly object $state,
    ) {}

    // ── Drawing ──
    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        // Background
        $ctx->fillRect(0, 0, (float) $params->areaWidth, (float) $params->areaHeight,
            Brush::rgb(BG_COLOR));

        // Grid lines (subtle)
        $gridColor = Brush::color(Color::rgba(1.0, 1.0, 1.0, 0.05));
        for ($x = 0; $x <= COLS; ++$x) {
            $px = (float) ($x * CELL);
            $ctx->strokeLine($px, 0.0, $px, BOARD_H, $gridColor);
        }
        for ($y = 0; $y <= ROWS; ++$y) {
            $py = (float) ($y * CELL);
            $ctx->strokeLine(0.0, $py, BOARD_W, $py, $gridColor);
        }

        // Locked cells
        for ($r = 0; $r < ROWS; ++$r) {
            for ($c = 0; $c < COLS; ++$c) {
                $type = $this->state->board[$r][$c];
                if ($type === -1) {
                    continue;
                }
                drawCell($ctx, $c, $r, PIECE_COLORS[$type]);
            }
        }

        // Ghost piece (where the current piece would land)
        if (!$this->state->gameOver && !$this->state->paused && $this->state->currentShape !== []) {
            $this->drawGhost($ctx);
        }

        // Current piece
        if (!$this->state->gameOver && $this->state->currentShape !== []) {
            $color = PIECE_COLORS[$this->state->currentType];
            foreach ($this->state->currentShape as $r => $row) {
                foreach ($row as $c => $cell) {
                    if ($cell === 0) {
                        continue;
                    }
                    drawCell($ctx,
                        $this->state->currentX + $c,
                        $this->state->currentY + $r,
                        $color);
                }
            }
        }

        // Game-over overlay
        if ($this->state->gameOver) {
            $this->drawOverlay($ctx, 'GAME OVER', 0xEF4444, 'Press R to restart');
        }

        // Paused overlay
        if ($this->state->paused) {
            $this->drawOverlay($ctx, 'PAUSED', 0xFBBF24, 'Press Esc to resume');
        }
    }

    // ── Keyboard ──
    public function key(AreaKeyEvent $event): bool
    {
        if ($event->up) {
            return false;
        }

        // R — restart (always available)
        $restart = $GLOBALS['_restart'] ?? null;
        if (($event->key === ord('r') || $event->key === ord('R')) && $restart !== null) {
            $restart();
            return true;
        }

        if ($this->state->gameOver) {
            return false;
        }

        // Escape — toggle pause
        if ($event->extKey === ExtKey::Escape->value) {
            $this->state->paused = !$this->state->paused;
            $this->state->gameArea?->queueRedrawAll();
            return true;
        }

        if ($this->state->paused) {
            return false;
        }

        // Retrieve game logic closures (injected via global for closure-in-class workaround)
        $isValid  = $GLOBALS['_isValid'] ?? null;
        $hardDrop = $GLOBALS['_hardDrop'] ?? null;

        switch ($event->extKey) {
            case ExtKey::Left->value:
                if ($isValid !== null && $isValid($this->state->currentShape, $this->state->currentX - 1, $this->state->currentY)) {
                    --$this->state->currentX;
                }
                break;

            case ExtKey::Right->value:
                if ($isValid !== null && $isValid($this->state->currentShape, $this->state->currentX + 1, $this->state->currentY)) {
                    ++$this->state->currentX;
                }
                break;

            case ExtKey::Down->value:
                if ($isValid !== null && $isValid($this->state->currentShape, $this->state->currentX, $this->state->currentY + 1)) {
                    ++$this->state->currentY;
                    $this->state->score += 1;
                    $this->state->scoreLabel?->setText("Score: {$this->state->score}");
                }
                break;

            case ExtKey::Up->value:
                $this->tryRotate($isValid);
                break;

            default:
                // Space bar — hard drop
                if ($event->key === ord(' ')) {
                    if ($hardDrop !== null) {
                        $hardDrop();
                    }
                }
                return true;
        }

        $this->state->gameArea?->queueRedrawAll();
        $this->state->previewArea?->queueRedrawAll();
        return true;
    }

    // ── Internal Helpers ──

    /** Draw the ghost (landing preview) for the current piece. */
    private function drawGhost(DrawContext $ctx): void
    {
        $ghostY = $this->state->currentY;
        while (true) {
            $ok = true;
            foreach ($this->state->currentShape as $r => $row) {
                foreach ($row as $c => $cell) {
                    if ($cell === 0) {
                        continue;
                    }
                    $x = $this->state->currentX + $c;
                    $y = $ghostY + $r + 1;
                    if ($x < 0 || $x >= COLS || $y >= ROWS) {
                        $ok = false;
                        break;
                    }
                    if ($y >= 0 && $this->state->board[$y][$x] !== -1) {
                        $ok = false;
                        break;
                    }
                }
                if (!$ok) {
                    break;
                }
            }
            if (!$ok) {
                break;
            }
            ++$ghostY;
        }

        $color = PIECE_COLORS[$this->state->currentType];
        $ghostFill  = Brush::color(Color::rgb($color, 0.15));
        $ghostStroke = Brush::color(Color::rgb($color, 0.35));
        $sp = (new StrokeParams())->thickness(1);

        foreach ($this->state->currentShape as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === 0) {
                    continue;
                }
                $px = ($this->state->currentX + $c) * CELL + 1;
                $py = ($ghostY + $r) * CELL + 1;
                $ctx->fillRect((float) $px, (float) $py, CELL - 2, CELL - 2, $ghostFill);
                $ctx->strokeRect((float) ($px - 1), (float) ($py - 1), (float) CELL, (float) CELL,
                    $ghostStroke, $sp);
            }
        }
    }

    /** Try rotating the current piece with wall kicks. */
    private function tryRotate(?callable $isValid): void
    {
        if ($isValid === null) {
            return;
        }

        $rotated = rotateMatrix($this->state->currentShape);
        $kicks = [[0, 0], [-1, 0], [1, 0], [0, -1], [-2, 0], [2, 0]];

        foreach ($kicks as [$dx, $dy]) {
            if ($isValid($rotated, $this->state->currentX + $dx, $this->state->currentY + $dy)) {
                $this->state->currentShape = $rotated;
                $this->state->currentX += $dx;
                $this->state->currentY += $dy;
                return;
            }
        }
    }

    /** Draw a centred overlay text over the game board. */
    private function drawOverlay(DrawContext $ctx, string $title, int $color, string $subtitle): void
    {
        $ctx->fillRect(0.0, 0.0, BOARD_W, BOARD_H,
            Brush::color(Color::rgba(0.0, 0.0, 0.0, 0.6)));

        $bigFont = new FontDescriptor('Helvetica', 28, TextWeight::Bold);
        $ctx->drawString(
            $title,
            $bigFont,
            Color::rgb($color),
            0.0,
            BOARD_H / 2 - 20,
            BOARD_W,
            DrawTextAlign::Center,
        );

        $smallFont = new FontDescriptor('Helvetica', 13);
        $ctx->drawString(
            $subtitle,
            $smallFont,
            Color::rgb(0x94A3B8),
            0.0,
            BOARD_H / 2 + 15,
            BOARD_W,
            DrawTextAlign::Center,
        );
    }
};

// ═════════════════════════════════════════════════════════════════════════════
// UI — Preview (Next Piece) Area Delegate
// ═════════════════════════════════════════════════════════════════════════════

$previewDelegate = new class ($state) extends AreaDelegate {
    public function __construct(
        private readonly object $state,
    ) {}

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $aw = (float) $params->areaWidth;
        $ah = (float) $params->areaHeight;

        $ctx->fillRect(0.0, 0.0, $aw, $ah,
            Brush::rgb(0x1E293B)); // slate-800

        $ctx->strokeRect(0.0, 0.0, $aw, $ah,
            Brush::rgb(0x334155), (new StrokeParams())->thickness(1));

        if ($this->state->gameOver) {
            return;
        }

        $shape = pieceShape($this->state->nextType);
        $color = PIECE_COLORS[$this->state->nextType];

        $rows = count($shape);
        $cols = count($shape[0]);
        // Fit cells to available width, max 20px
        $pc = min(20.0, ($aw - 12.0) / max($cols, $rows));
        $totalW = $cols * $pc;
        $totalH = $rows * $pc;
        $ox = ($aw - $totalW) / 2.0;
        $oy = ($ah - $totalH) / 2.0;

        foreach ($shape as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === 0) {
                    continue;
                }
                $ctx->fillRect(
                    $ox + $c * $pc + 1,
                    $oy + $r * $pc + 1,
                    $pc - 2,
                    $pc - 2,
                    Brush::rgb($color),
                );
            }
        }
    }
};

// ═════════════════════════════════════════════════════════════════════════════
// UI — Assembly
// ═════════════════════════════════════════════════════════════════════════════

$gameArea    = new Area($gameDelegate);
$previewArea = new Area($previewDelegate);

$state->gameArea    = $gameArea;
$state->previewArea = $previewArea;

// Inject game-logic closures into $GLOBALS so the anonymous-class delegate
// (which cannot capture outer-scope variables via `use`) can access them.
$GLOBALS['_isValid']  = $isValid;
$GLOBALS['_hardDrop'] = $hardDrop;
$GLOBALS['_restart']  = $restart;

// ── Layout ──
$sidebar = Build::vbox(
    new Label('TETRIS'),
    new Label(''),
    $scoreLabel,
    $levelLabel,
    $linesLabel,
    new Label(''),
    Build::stretchy(Group::titled('NEXT', $previewArea)),
);

$window = new Window('Tetris', BOARD_W + SIDEBAR_W + 30, BOARD_H + 90, false);
$window->setMargined(true);
$window->setChild(Build::hbox(
    Build::stretchy($gameArea),
    $sidebar,
));

// ── Start ──
$restart();

// ═════════════════════════════════════════════════════════════════════════════
// RUN
// ═════════════════════════════════════════════════════════════════════════════

App::new()
    ->window($window)
    ->onShouldQuit(fn (): bool => true)
    ->run();

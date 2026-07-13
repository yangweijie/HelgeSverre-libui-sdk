<?php

declare(strict_types=1);

/**
 * Tetris — a full-featured Tetris game.
 *
 * Architecture:
 *   Single Area + AreaDelegate draws everything:
 *     - Left:  game board (grid, pieces, ghost, overlays)
 *     - Right: sidebar (title, score, level, lines, NEXT, preview)
 *   All labels drawn via DrawContext::drawString() — fully self-drawn.
 *
 * Controls:
 *   ← → ↓  Move   ↑  Rotate   Space  Hard drop   R  Restart   Esc  Pause
 *
 * Run: php85 examples/tetris.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Area;
use Libui\AreaDelegate;
use Libui\Color;
use Libui\Draw\DrawContext;
use Libui\Draw\Brush;
use Libui\Draw\StrokeParams;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaKeyEvent;
use Libui\Generated\Enum\ExtKey;
use Libui\Generated\Enum\TextWeight;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Loop;
use Libui\Text\FontDescriptor;
use Libui\Window;

// ═════════════════════════════════════════════════════════════════════════════
// CONSTANTS
// ═════════════════════════════════════════════════════════════════════════════

define('COLS', 10);
define('ROWS', 20);
define('CELL', 30);
define('BOARD_W', COLS * CELL);
define('BOARD_H', ROWS * CELL);
define('SIDEBAR_W', 180);

define('SIDEBAR_BG', 0x1E293B);
define('BG_COLOR', 0x0F172A);

define('PIECE_COLORS', [
    0x00F0F0, 0xF0F000, 0xA000F0, 0x00F000, 0xF00000, 0x0000F0, 0xF0A000,
]);

// ═════════════════════════════════════════════════════════════════════════════
// SHAPE HELPERS
// ═════════════════════════════════════════════════════════════════════════════

function pieceShape(int $type): array
{
    static $shapes = [
        [[0,0,0,0],[1,1,1,1],[0,0,0,0],[0,0,0,0]],
        [[1,1],[1,1]],
        [[0,1,0],[1,1,1],[0,0,0]],
        [[0,1,1],[1,1,0],[0,0,0]],
        [[1,1,0],[0,1,1],[0,0,0]],
        [[1,0,0],[1,1,1],[0,0,0]],
        [[0,0,1],[1,1,1],[0,0,0]],
    ];
    return $shapes[$type];
}

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

function dropInterval(int $level): int
{
    return max(80, 1000 - ($level - 1) * 80);
}

// ═════════════════════════════════════════════════════════════════════════════
// GAME STATE
// ═════════════════════════════════════════════════════════════════════════════

$state = new class {
    public array $board;
    public int $currentType = 0;
    public array $currentShape = [];
    public int $currentX = 3;
    public int $currentY = 0;
    public int $nextType = 0;
    public int $score = 0;
    public int $level = 1;
    public int $totalLines = 0;
    public bool $gameOver = false;
    public bool $paused = false;
    public ?int $timerId = null;
    public ?Area $gameArea = null;

    public function __construct()
    {
        $this->board = array_fill(0, ROWS, array_fill(0, COLS, -1));
    }
};

// ═════════════════════════════════════════════════════════════════════════════
// DRAW HELPERS
// ═════════════════════════════════════════════════════════════════════════════

function drawCell(DrawContext $ctx, int $col, int $row, int $hexColor, float $boardY = 0.0): void
{
    $x = $col * CELL + 1;
    $y = $row * CELL + 1 + $boardY;
    $size = CELL - 2;
    $ctx->fillRect((float) $x, (float) $y, (float) $size, (float) $size, Brush::rgb($hexColor));
    $hl = Brush::color(Color::rgba(1.0, 1.0, 1.0, 0.35));
    $ctx->fillRect((float) ($x - 1), (float) ($y - 1), (float) ($size + 2), 2.0, $hl);
    $ctx->fillRect((float) ($x - 1), (float) ($y - 1), 2.0, (float) ($size + 2), $hl);
    $sh = Brush::color(Color::rgba(0.0, 0.0, 0.0, 0.35));
    $ctx->fillRect((float) ($x - 1), (float) ($y + $size - 1), (float) ($size + 2), 2.0, $sh);
    $ctx->fillRect((float) ($x + $size - 1), (float) ($y - 1), 2.0, (float) ($size + 2), $sh);
}

function drawLabel(DrawContext $ctx, string $text, float $x, float $y, float $size, ?int $hexColor = null, float $opacity = 1.0): void
{
    $c = $hexColor !== null ? Color::rgb($hexColor) : Color::rgb(0xE2E8F0);
    $alpha = max(0.0, min(1.0, $opacity));
    $color = Color::rgba($c->r, $c->g, $c->b, $alpha);
    $font = new FontDescriptor('Helvetica', $size);
    $ctx->drawString($text, $font, $color, $x, $y, SIDEBAR_W - 24, DrawTextAlign::Left);
}

// ═════════════════════════════════════════════════════════════════════════════
// GAME-LOGIC CLOSURES
// ═════════════════════════════════════════════════════════════════════════════

$isValid = function (array $shape, int $offX, int $offY) use ($state): bool {
    foreach ($shape as $r => $row) {
        foreach ($row as $c => $cell) {
            if ($cell === 0) continue;
            $x = $offX + $c;
            $y = $offY + $r;
            if ($x < 0 || $x >= COLS || $y >= ROWS) return false;
            if ($y >= 0 && $state->board[$y][$x] !== -1) return false;
        }
    }
    return true;
};

$lockPiece = function () use ($state, &$isValid): void {
    $shape = $state->currentShape;
    $type = $state->currentType;

    foreach ($shape as $r => $row) {
        foreach ($row as $c => $cell) {
            if ($cell === 0) continue;
            $x = $state->currentX + $c;
            $y = $state->currentY + $r;
            if ($y >= 0 && $y < ROWS && $x >= 0 && $x < COLS) {
                $state->board[$y][$x] = $type;
            }
        }
    }

    $cleared = 0;
    for ($y = ROWS - 1; $y >= 0; --$y) {
        $full = true;
        for ($x = 0; $x < COLS; ++$x) {
            if ($state->board[$y][$x] === -1) { $full = false; break; }
        }
        if ($full) {
            array_splice($state->board, $y, 1);
            array_unshift($state->board, array_fill(0, COLS, -1));
            ++$cleared;
            ++$y;
        }
    }

    if ($cleared > 0) {
        $points = [0, 100, 300, 500, 800];
        $state->score += ($points[$cleared] ?? 800) * $state->level;
        $state->totalLines += $cleared;
        $state->level = (int) floor($state->totalLines / 10) + 1;
    }

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

$tick = null;
$tick = function () use ($state, &$isValid, &$lockPiece, &$tick): bool {
    if ($state->gameOver) return false;
    if ($state->paused) return true;

    if ($isValid($state->currentShape, $state->currentX, $state->currentY + 1)) {
        ++$state->currentY;
    } else {
        $lockPiece();
    }

    $state->gameArea?->queueRedrawAll();

    if (!$state->gameOver) {
        Loop::cancel($state->timerId);
        $state->timerId = Loop::repeat(dropInterval($state->level), $tick);
    }
    return true;
};

$hardDrop = function () use ($state, &$isValid, &$lockPiece): void {
    if ($state->gameOver || $state->paused) return;
    $rows = 0;
    while ($isValid($state->currentShape, $state->currentX, $state->currentY + 1)) {
        ++$state->currentY;
        ++$rows;
    }
    $state->score += $rows * 2;
    $lockPiece();
    $state->gameArea?->queueRedrawAll();
};

$restart = function () use ($state, &$tick): void {
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
    $state->nextType = random_int(0, 6);
    $state->currentType = random_int(0, 6);
    $state->currentShape = pieceShape($state->currentType);
    $state->currentX = (int) ((COLS - count($state->currentShape[0])) / 2);
    $state->currentY = 0;
    $state->nextType = random_int(0, 6);
    $state->timerId = Loop::repeat(dropInterval(1), $tick);
    $state->gameArea?->queueRedrawAll();
};

// ═════════════════════════════════════════════════════════════════════════════
// AREA DELEGATE — draws game board + sidebar in one Area
// ═════════════════════════════════════════════════════════════════════════════

$delegate = new class ($state) extends AreaDelegate {
    public function __construct(private readonly object $state) {}

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $aw = (float) $params->areaWidth;
        $ah = (float) $params->areaHeight;

        // Board Y offset: center vertically within available area
        $boardY = max(0.0, ($ah - (float) BOARD_H) / 2.0);

        // ── Background ──────────────────────────────────────────────────
        $ctx->fillRect(0, 0, $aw, $ah, Brush::rgb(BG_COLOR));

        // Sidebar background (right column)
        $sbx = (float) BOARD_W;
        $ctx->fillRect($sbx, 0.0, $aw - $sbx, $ah, Brush::rgb(SIDEBAR_BG));
        $ctx->strokeLine($sbx, 0.0, $sbx, $ah, Brush::color(Color::rgba(1.0, 1.0, 1.0, 0.08)));

        // ── Game board (vertically centered) ─────────────────────────────
        $this->drawBoard($ctx, $boardY);
        $this->drawLockedCells($ctx, $boardY);

        if (!$this->state->gameOver && !$this->state->paused && $this->state->currentShape !== []) {
            $this->drawGhost($ctx, $boardY);
        }

        if (!$this->state->gameOver && $this->state->currentShape !== []) {
            $color = PIECE_COLORS[$this->state->currentType];
            foreach ($this->state->currentShape as $r => $row) {
                foreach ($row as $c => $cell) {
                    if ($cell === 0) continue;
                    $cx = $this->state->currentX + $c;
                    $cy = $this->state->currentY + $r;
                    drawCell($ctx, $cx, $cy, $color, $boardY);
                }
            }
        }

        if ($this->state->gameOver) $this->drawOverlay($ctx, 'GAME OVER', 0xEF4444, 'Press R to restart', $boardY);
        if ($this->state->paused) $this->drawOverlay($ctx, 'PAUSED', 0xFBBF24, 'Press Esc to resume', $boardY);

        // ── Sidebar ─────────────────────────────────────────────────────
        $sx = $sbx + 12.0;
        $sy = 16.0;

        drawLabel($ctx, 'TETRIS', $sx, $sy, 22.0, 0xE2E8F0);
        $sy += 36.0;

        drawLabel($ctx, "Score: {$this->state->score}", $sx, $sy, 14.0, 0xE2E8F0);
        $sy += 24.0;

        drawLabel($ctx, "Level: {$this->state->level}", $sx, $sy, 14.0, 0xE2E8F0);
        $sy += 24.0;

        drawLabel($ctx, "Lines: {$this->state->totalLines}", $sx, $sy, 14.0, 0xE2E8F0);
        $sy += 28.0;

        drawLabel($ctx, 'NEXT', $sx, $sy, 12.0, 0x94A3B8, 0.6);
        $sy += 22.0;

        $this->drawPreview($ctx, $sx, $sy, $aw - $sbx - 24.0, 100.0);
    }

    public function key(AreaKeyEvent $event): bool
    {
        if ($event->up) return false;

        $restart = $GLOBALS['_restart'] ?? null;
        if (($event->key === ord('r') || $event->key === ord('R')) && $restart !== null) {
            $restart();
            return true;
        }

        if ($this->state->gameOver) return false;

        if ($event->extKey === ExtKey::Escape->value) {
            $this->state->paused = !$this->state->paused;
            $this->state->gameArea?->queueRedrawAll();
            return true;
        }

        if ($this->state->paused) return false;

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
                }
                break;
            case ExtKey::Up->value:
                $this->tryRotate($isValid);
                break;
            default:
                if ($event->key === ord(' ') && $hardDrop !== null) $hardDrop();
                return true;
        }

        $this->state->gameArea?->queueRedrawAll();
        return true;
    }

    private function drawBoard(DrawContext $ctx, float $boardY): void
    {
        $gridColor = Brush::color(Color::rgba(1.0, 1.0, 1.0, 0.06));
        for ($x = 0; $x <= COLS; ++$x) {
            $px = (float) ($x * CELL);
            $ctx->strokeLine($px, $boardY, $px, $boardY + (float) BOARD_H, $gridColor);
        }
        for ($y = 0; $y <= ROWS; ++$y) {
            $py = (float) ($y * CELL) + $boardY;
            $ctx->strokeLine(0.0, $py, (float) BOARD_W, $py, $gridColor);
        }
        $border = Brush::color(Color::rgba(1.0, 1.0, 1.0, 0.15));
        $ctx->strokeRect(0.0, $boardY, (float) BOARD_W, (float) BOARD_H, $border, (new StrokeParams())->thickness(1));
    }

    private function drawLockedCells(DrawContext $ctx, float $boardY): void
    {
        for ($r = 0; $r < ROWS; ++$r) {
            for ($c = 0; $c < COLS; ++$c) {
                $type = $this->state->board[$r][$c];
                if ($type !== -1) drawCell($ctx, $c, $r, PIECE_COLORS[$type], $boardY);
            }
        }
    }

    private function drawGhost(DrawContext $ctx, float $boardY): void
    {
        $ghostY = $this->state->currentY;
        while (true) {
            $ok = true;
            foreach ($this->state->currentShape as $r => $row) {
                foreach ($row as $c => $cell) {
                    if ($cell === 0) continue;
                    $x = $this->state->currentX + $c;
                    $y = $ghostY + $r + 1;
                    if ($x < 0 || $x >= COLS || $y >= ROWS) { $ok = false; break; }
                    if ($y >= 0 && $this->state->board[$y][$x] !== -1) { $ok = false; break; }
                }
                if (!$ok) break;
            }
            if (!$ok) break;
            ++$ghostY;
        }

        $color = PIECE_COLORS[$this->state->currentType];
        $ghostFill  = Brush::color(Color::rgb($color, 0.15));
        $ghostStroke = Brush::color(Color::rgb($color, 0.35));
        $sp = (new StrokeParams())->thickness(1);

        foreach ($this->state->currentShape as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === 0) continue;
                $px = ($this->state->currentX + $c) * CELL + 1;
                $py = ($ghostY + $r) * CELL + 1 + $boardY;
                $ctx->fillRect((float) $px, (float) $py, (float) (CELL - 2), (float) (CELL - 2), $ghostFill);
                $ctx->strokeRect((float) ($px - 1), (float) ($py - 1), (float) CELL, (float) CELL, $ghostStroke, $sp);
            }
        }
    }

    private function drawPreview(DrawContext $ctx, float $x, float $y, float $w, float $h): void
    {
        // Preview background
        $ctx->fillRect($x, $y, $w, $h, Brush::rgb(0x0F172A));
        $ctx->strokeRect($x, $y, $w, $h, Brush::rgb(0x334155), (new StrokeParams())->thickness(1));

        if ($this->state->gameOver) return;

        $shape = pieceShape($this->state->nextType);
        $hexColor = PIECE_COLORS[$this->state->nextType];
        $rows = count($shape);
        $cols = count($shape[0]);
        $pc = min(20.0, ($w - 12.0) / max($cols, $rows));
        $totalW = $cols * $pc;
        $totalH = $rows * $pc;
        $ox = $x + ($w - $totalW) / 2.0;
        $oy = $y + ($h - $totalH) / 2.0;

        foreach ($shape as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === 0) continue;
                $cx = $ox + $c * $pc + 1;
                $cy = $oy + $r * $pc + 1;
                $ctx->fillRect($cx, $cy, $pc - 2, $pc - 2, Brush::rgb($hexColor));
            }
        }
    }

    private function tryRotate(?callable $isValid): void
    {
        if ($isValid === null) return;
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

    private function drawOverlay(DrawContext $ctx, string $title, int $color, string $subtitle, float $boardY): void
    {
        // Semi-transparent overlay on game board
        $ctx->fillRect(0.0, $boardY, (float) BOARD_W, (float) BOARD_H, Brush::color(Color::rgba(0.0, 0.0, 0.0, 0.6)));

        // Draw title centered manually (DrawTextAlign::Center unreliable on macOS)
        $bigFont = new FontDescriptor('Helvetica', 28, TextWeight::Bold);
        $titleStr = new \Libui\Text\AttributedString();
        $titleStr->append($title, \Libui\Text\Attribute::size(28));
        $titleLayout = new \Libui\Text\TextLayout($titleStr, $bigFont, (float) BOARD_W);
        [$tw, $th] = $titleLayout->extents();
        $titleX = ((float) BOARD_W - $tw) / 2.0;
        $titleY = $boardY + ((float) BOARD_H - $th) / 2.0 - 10.0;
        $ctx->drawString($title, $bigFont, Color::rgb($color), $titleX, $titleY, $tw, DrawTextAlign::Left);
        $titleLayout->free();
        $titleStr->free();

        // Draw subtitle centered below title
        $smallFont = new FontDescriptor('Helvetica', 13);
        $subStr = new \Libui\Text\AttributedString();
        $subStr->append($subtitle, \Libui\Text\Attribute::size(13));
        $subLayout = new \Libui\Text\TextLayout($subStr, $smallFont, (float) BOARD_W);
        [$sw, $sh] = $subLayout->extents();
        $subX = ((float) BOARD_W - $sw) / 2.0;
        $subY = $titleY + $th + 8.0;
        $ctx->drawString($subtitle, $smallFont, Color::rgb(0x94A3B8), $subX, $subY, $sw, DrawTextAlign::Left);
        $subLayout->free();
        $subStr->free();
    }
};

// ═════════════════════════════════════════════════════════════════════════════
// ASSEMBLY
// ═════════════════════════════════════════════════════════════════════════════

$gameArea = new Area($delegate);
$state->gameArea = $gameArea;

$GLOBALS['_isValid']  = $isValid;
$GLOBALS['_hardDrop'] = $hardDrop;
$GLOBALS['_restart']  = $restart;

$window = new Window('Tetris', BOARD_W + SIDEBAR_W + 40, BOARD_H + 60, false);
$window->setMargined(true);
$window->setChild($gameArea);

$restart();

App::new()
    ->window($window)
    ->onShouldQuit(fn (): bool => true)
    ->run();

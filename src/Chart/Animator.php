<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

use Libui\Loop;

/**
 * Drives value transitions for animated data updates.
 *
 * Each frame interpolates every dataset's values between the previous and the
 * new state with an ease-out cubic curve, invoking $onFrame so the chart can
 * repaint. When the chart is bound to a live Area the tween is pumped by
 * {@see Loop::repeat}; when headless (no Area, e.g. in tests) the caller drives
 * it manually via {@see seekTo()} for deterministic assertions.
 */
final class Animator
{
    private array $from = [];
    private array $to = [];
    private float $duration = 600.0;
    private float $elapsed = 0.0;
    private bool $running = false;
    private ?int $timerId = null;
    /** @var callable(array<int,array<float>>):void|null */
    private $onFrame = null;
    /** @var callable():void|null */
    private $onDone = null;

    /**
     * @param list<array<float>>              $from     Previous values.
     * @param list<array<float>>              $to       Target values.
     * @param float                           $durationMs Tween length.
     * @param callable(array<int,array<float>>):void $onFrame Called each frame.
     * @param callable():void                 $onDone   Called once at t=1.
     */
    public function animate(array $from, array $to, float $durationMs, callable $onFrame, callable $onDone): void
    {
        $this->cancel();
        $this->from = $from;
        $this->to = $to;
        $this->duration = max(1.0, $durationMs);
        $this->elapsed = 0.0;
        $this->running = true;
        $this->onFrame = $onFrame;
        $this->onDone = $onDone;

        // A bound Area means we're inside the GUI event loop, so a real timer
        // will actually fire. Headless (no Area) relies on manual seekTo().
        $this->timerId = Loop::repeat(16, fn () => $this->advance());
    }

    /** Pump one ~16ms step. Safe to call repeatedly. */
    public function advance(): void
    {
        if (! $this->running) {
            return;
        }
        $this->elapsed += 16.0;
        $t = min(1.0, $this->elapsed / $this->duration);
        $this->apply($t);
        if ($t >= 1.0) {
            $this->finish();
        }
    }

    /** Jump to absolute progress $t in [0,1] — used by tests and for scrubbing. */
    public function seekTo(float $t): void
    {
        if (! $this->running) {
            return;
        }
        $this->elapsed = max(0.0, min(1.0, $t)) * $this->duration;
        $this->apply($t);
        if ($t >= 1.0) {
            $this->finish();
        }
    }

    public function cancel(): void
    {
        if ($this->timerId !== null) {
            Loop::cancel($this->timerId);
            $this->timerId = null;
        }
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    private function apply(float $t): void
    {
        $eased = self::easeOutCubic($t);
        $values = self::lerp($this->from, $this->to, $eased);
        ($this->onFrame)($values);
    }

    private function finish(): void
    {
        $this->running = false;
        if ($this->timerId !== null) {
            Loop::cancel($this->timerId);
            $this->timerId = null;
        }
        ($this->onDone)();
    }

    public static function easeOutCubic(float $t): float
    {
        return 1.0 - (1.0 - $t) ** 3;
    }

    /**
     * Interpolate a list of series, padding mismatched lengths with 0 so points
     * gracefully grow in / shrink out when the data count changes.
     *
     * @param list<array<float>> $from
     * @param list<array<float>> $to
     * @return list<array<float>>
     */
    public static function lerp(array $from, array $to, float $e): array
    {
        $n = max(count($from), count($to));
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $a = $from[$i] ?? [];
            $b = $to[$i] ?? [];
            $m = max(count($a), count($b));
            $row = [];
            for ($j = 0; $j < $m; $j++) {
                $av = $a[$j] ?? 0.0;
                $bv = $b[$j] ?? 0.0;
                $row[$j] = $av + ($bv - $av) * $e;
            }
            $out[$i] = $row;
        }

        return $out;
    }
}

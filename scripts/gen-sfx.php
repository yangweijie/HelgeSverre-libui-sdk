<?php

declare(strict_types=1);

/**
 * Procedural sound-effect synthesizer for the One Piece Doudizhu game.
 *
 * Generates 9 monophonic 16-bit PCM WAV files into assets/audio/ using only
 * PHP (sine / square / triangle / saw oscillators + white noise + AD envelopes
 * + pitch sweeps). No external assets or audio libraries required.
 *
 * Usage:
 *   php85 scripts/gen-sfx.php
 *   php85 scripts/gen-sfx.php --out=/custom/path
 *
 * Output (assets/audio/):
 *   click.wav  deal.wav  play.wav  pass.wav  bomb.wav
 *   skill.wav  bid.wav   win.wav   lose.wav
 */

const SR = 44100; // sample rate

/* ----------------------------- primitives ----------------------------- */

function tone(float $freq, float $dur, string $wave = 'sine', float $phase = 0.0): array
{
    $n = (int) (SR * $dur);
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $t = $i / SR;
        $ph = 2 * M_PI * $freq * $t + $phase;
        $out[] = match ($wave) {
            'square' => (($ph / (2 * M_PI)) % 1.0) < 0.5 ? 1.0 : -1.0,
            'saw'    => 2 * ($freq * $t - floor(0.5 + $freq * $t)),
            'tri'    => 2 * abs(2 * ($freq * $t - floor(0.5 + $freq * $t))) - 1,
            default  => sin($ph),
        };
    }
    return $out;
}

function noise(float $dur, float $amp = 1.0): array
{
    $n = (int) (SR * $dur);
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $out[] = (random_int(-1000, 1000) / 1000.0) * $amp;
    }
    return $out;
}

function sweep(float $f0, float $f1, float $dur): array
{
    $n = (int) (SR * $dur);
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $t = $i / $n;
        $freq = $f0 + ($f1 - $f0) * $t;
        $out[] = sin(2 * M_PI * $freq * ($i / SR));
    }
    return $out;
}

/* ------------------------------ helpers ------------------------------- */

/** Attack / hold / decay envelope (linear). */
function env(array $s, float $a, float $h, float $d, float $sus = 1.0): array
{
    $n = count($s);
    $ia = (int) (SR * $a);
    $ih = (int) (SR * $h);
    $id = (int) (SR * $d);
    for ($i = 0; $i < $n; $i++) {
        $amp = 1.0;
        if ($i < $ia) {
            $amp = $ia > 0 ? $i / $ia : 1.0;
        } elseif ($i < $ia + $ih) {
            $amp = $sus;
        } elseif ($i < $ia + $ih + $id) {
            $amp = $sus * (1 - ($i - $ia - $ih) / max(1, $id));
        } else {
            $amp = 0.0;
        }
        $s[$i] *= $amp;
    }
    return $s;
}

/** Prepend silence to shift a track in time. */
function delay(array $s, float $sec): array
{
    $pad = array_fill(0, (int) (SR * $sec), 0.0);
    return array_merge($pad, $s);
}

/** Sum any number of tracks (zero-padded to the longest). */
function mix(array ...$tracks): array
{
    $len = 0;
    foreach ($tracks as $t) {
        $len = max($len, count($t));
    }
    $out = array_fill(0, $len, 0.0);
    foreach ($tracks as $t) {
        foreach ($t as $i => $v) {
            $out[$i] += $v;
        }
    }
    return $out;
}

/** Single enveloped note. */
function note(float $freq, float $dur, string $wave, float $a, float $h, float $d): array
{
    return env(tone($freq, $dur, $wave), $a, $h, $d);
}

/** Staggered ascending/descending arpeggio. */
function arpeggio(array $freqs, float $noteDur, float $stagger, string $wave, float $decay): array
{
    $out = [];
    foreach ($freqs as $k => $f) {
        $out = mix($out, delay(note($f, $noteDur, $wave, 0.01, 0.02, $decay), $k * $stagger));
    }
    return $out;
}

/* ------------------------------- sounds ------------------------------- */

function sndClick(): array
{
    return env(tone(1300, 0.06, 'sine'), 0.001, 0.0, 0.055);
}

function sndDeal(): array
{
    $swish = fn (float $amp): array => env(noise(0.08, $amp), 0.002, 0.0, 0.07);
    return mix(
        $swish(0.55),
        delay($swish(0.5), 0.12),
    );
}

function sndPlay(): array
{
    return mix(
        note(660, 0.14, 'sine', 0.005, 0.01, 0.12),
        note(990, 0.10, 'sine', 0.005, 0.01, 0.09),
    );
}

function sndPass(): array
{
    return mix(
        note(330, 0.20, 'sine', 0.01, 0.0, 0.18),
        delay(note(220, 0.20, 'sine', 0.01, 0.0, 0.18), 0.14),
    );
}

function sndBomb(): array
{
    return mix(
        env(sweep(150, 38, 0.55), 0.005, 0.0, 0.54),
        env(noise(0.32, 0.7), 0.002, 0.0, 0.30),
    );
}

function sndSkill(): array
{
    $arp = arpeggio([523.25, 659.25, 783.99, 1046.5], 0.18, 0.07, 'sine', 0.15);
    $sparkle = delay(env(noise(0.4, 0.22), 0.05, 0.1, 0.25), 0.05);
    return mix($arp, $sparkle);
}

function sndBid(): array
{
    return mix(
        note(440, 0.18, 'tri', 0.01, 0.02, 0.14),
        delay(note(660, 0.24, 'tri', 0.01, 0.03, 0.2), 0.16),
    );
}

function sndWin(): array
{
    return arpeggio([523.25, 659.25, 783.99, 1046.5], 0.26, 0.18, 'tri', 0.22);
}

function sndLose(): array
{
    return arpeggio([440.0, 349.23, 293.66, 220.0], 0.26, 0.18, 'tri', 0.24);
}

const SOUNDS = [
    'click' => 'sndClick',
    'deal'  => 'sndDeal',
    'play'  => 'sndPlay',
    'pass'  => 'sndPass',
    'bomb'  => 'sndBomb',
    'skill' => 'sndSkill',
    'bid'   => 'sndBid',
    'win'   => 'sndWin',
    'lose'  => 'sndLose',
];

/* ----------------------------- WAV write ------------------------------ */

function writeWav(string $path, array $samples): void
{
    $peak = 0.0001;
    foreach ($samples as $v) {
        $peak = max($peak, abs($v));
    }
    $gain = 0.9 / $peak;
    $n = count($samples);
    $data = '';
    for ($i = 0; $i < $n; $i++) {
        $v = (int) round($samples[$i] * $gain * 32767);
        if ($v > 32767) {
            $v = 32767;
        }
        if ($v < -32768) {
            $v = -32768;
        }
        $data .= pack('v', $v & 0xFFFF); // little-endian 16-bit
    }

    $byteRate = SR * 1 * 2;
    $blockAlign = 2;
    $header = "RIFF"
        . pack('V', 36 + $n * 2)
        . "WAVE"
        . "fmt "
        . pack('V', 16)
        . pack('v', 1)        // PCM
        . pack('v', 1)        // mono
        . pack('V', SR)
        . pack('V', $byteRate)
        . pack('v', $blockAlign)
        . pack('v', 16);
    $header .= "data" . pack('V', $n * 2);

    file_put_contents($path, $header . $data);
}

/* ------------------------------- main --------------------------------- */

$out = 'assets/audio';
foreach ($_SERVER['argv'] as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $out = substr($arg, 6);
    }
}

if (!is_dir($out)) {
    mkdir($out, 0755, true);
}

$count = 0;
foreach (SOUNDS as $name => $fn) {
    /** @var array $samples */
    $samples = $fn();
    $path = $out . '/' . $name . '.wav';
    writeWav($path, $samples);
    $bytes = filesize($path);
    printf("  %-6s -> %s (%d bytes, %.2fs)\n", $name, $path, $bytes, $bytes / (SR * 2 + 44));
    $count++;
}

echo "\nGenerated {$count} sound effects into {$out}/\n";

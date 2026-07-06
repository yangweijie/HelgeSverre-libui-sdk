<?php

declare(strict_types=1);

use Yangweijie\Ui2\System\Audio;

test('Audio exposes engine + playback API', function () {
    $ref = new ReflectionClass(Audio::class);

    foreach (['init', 'shutdown', 'load'] as $m) {
        expect($ref->getMethod($m)->isStatic())->toBeTrue();
    }
    foreach (['play', 'pause', 'resume', 'stop', 'setVolume', 'setLooping', 'isPlaying', 'isLooping', 'getHandle', 'onEnded', 'unload'] as $m) {
        expect($ref->hasMethod($m))->toBeTrue();
    }
});

test('load throws RuntimeException on missing file', function () {
    expect(fn () => Audio::load('/no/such/file-' . uniqid() . '.wav'))
        ->toThrow(RuntimeException::class);
});

test('load + play + pause + resume lifecycle', function () {
    try {
        Audio::init();
    } catch (RuntimeException $e) {
        $this->markTestSkipped('Audio backend unavailable: ' . $e->getMessage());
    }

    $wav = makeSilentWav(8820); // 0.2s silent clip
    $sound = Audio::load($wav)
        ->setVolume(0.5)
        ->setLooping(false)
        ->onEnded(fn () => null)
        ->play();

    expect($sound->getHandle())->toBeGreaterThan(0);
    expect($sound->isPlaying())->toBeTrue();
    expect($sound->isLooping())->toBeFalse();

    $sound->pause();
    expect($sound->isPlaying())->toBeFalse();

    $sound->resume();
    expect($sound->isPlaying())->toBeTrue();

    $sound->stop();
    $sound->unload();
    Audio::shutdown();
    expect(true)->toBeTrue();
});

/**
 * Build a minimal valid 16-bit PCM mono WAV (all-zero samples = silence).
 */
function makeSilentWav(int $numSamples = 8820): string
{
    $data = str_repeat("\x00\x00", $numSamples);
    $header = 'RIFF'
        . pack('V', 36 + strlen($data))
        . 'WAVEfmt '
        . pack('V', 16)   // fmt chunk size
        . pack('v', 1)    // PCM
        . pack('v', 1)    // mono
        . pack('V', 44100)
        . pack('V', 88200) // byte rate (sr * channels * bytes/sample)
        . pack('v', 2)    // block align
        . pack('v', 16)   // bits/sample
        . 'data'
        . pack('V', strlen($data));

    $path = sys_get_temp_dir() . '/sv_audio_' . uniqid() . '.wav';
    file_put_contents($path, $header . $data);
    return $path;
}

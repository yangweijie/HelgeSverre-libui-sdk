<?php

declare(strict_types=1);

use Yangweijie\Ui2\Rendering\DesignTokens;

test('resolve navigates nested paths', function () {
    $t = new DesignTokens();

    expect($t->resolve('color.primary'))->toBe([0.04, 0.52, 1.0, 1.0]);
    expect($t->number('radius.md'))->toBe(8.0);
    expect($t->resolve('typography.body.size'))->toBe(14.0);
});

test('color() builds a Libui Color from a token', function () {
    $c = (new DesignTokens())->color('color.primary');

    expect($c->r)->toEqualWithDelta(0.04, 0.001);
    expect($c->g)->toEqualWithDelta(0.52, 0.001);
    expect($c->b)->toEqualWithDelta(1.0, 0.001);
    expect($c->a)->toBe(1.0);
});

test('applyTheme returns a new instance and never mutates the receiver', function () {
    $t = new DesignTokens();
    $next = $t->applyTheme(['color' => ['primary' => [0.0, 0.8, 0.0, 1.0]]]);

    // original untouched
    expect($t->color('color.primary')->g)->toEqualWithDelta(0.52, 0.001);
    // new carries the override
    expect($next->color('color.primary')->g)->toEqualWithDelta(0.8, 0.001);
    // unrelated tokens are carried over
    expect($next->color('color.track')->r)->toEqualWithDelta(0.88, 0.001);
});

test('has() reports existing and missing paths', function () {
    $t = new DesignTokens();

    expect($t->has('color.knob'))->toBeTrue();
    expect($t->has('color.nope'))->toBeFalse();
});

test('recursive token references are dereferenced', function () {
    $t = new DesignTokens();
    $next = $t->applyTheme([
        'color' => [
            'brand' => 'color.primary', // reference by dotted path
            'accent' => [1.0, 0.0, 0.0, 1.0],
        ],
    ]);

    // 'brand' resolves through to color.primary (still blue)
    expect($next->color('color.brand')->g)->toEqualWithDelta(0.52, 0.001);
    expect($next->color('color.accent')->r)->toBe(1.0);
});

test('missing token path throws', function () {
    (new DesignTokens())->resolve('color.does.not.exist');
})->throws(\OutOfBoundsException::class);

<?php

declare(strict_types=1);

use Yangweijie\Ui2\State\AppRuntime;
use Yangweijie\Ui2\State\Msg;
use Yangweijie\Ui2\State\Model;
use Yangweijie\Ui2\State\UpdateResult;

// ── Model ──────────────────────────────────────────────────────────────────

final readonly class CounterModel implements Model
{
    public function __construct(
        public int $count = 0,
    ) {}
}

// ── Msg ────────────────────────────────────────────────────────────────────

enum CounterMsg: string implements Msg
{
    case Inc = 'inc';
    case Dec = 'dec';
    case Reset = 'reset';
}

// ── Update ─────────────────────────────────────────────────────────────────

function counterUpdate(CounterModel $m, CounterMsg $msg): UpdateResult
{
    return match ($msg) {
        CounterMsg::Inc    => UpdateResult::pure(new CounterModel($m->count + 1)),
        CounterMsg::Dec    => UpdateResult::pure(new CounterModel($m->count - 1)),
        CounterMsg::Reset  => UpdateResult::pure(new CounterModel(0)),
    };
}

// ── Tests ──────────────────────────────────────────────────────────────────

test('AppRuntime initial model', function (): void {
    $app = new AppRuntime(new CounterModel(42), 'counterUpdate');

    /** @var CounterModel $m */
    $m = $app->model();

    expect($m)->toBeInstanceOf(CounterModel::class);
    expect($m->count)->toBe(42);
});

test('dispatch Inc increments', function (): void {
    $app = new AppRuntime(new CounterModel(0), 'counterUpdate');

    $m = $app->dispatch(CounterMsg::Inc);

    expect($m)->toBeInstanceOf(CounterModel::class);
    expect($m->count)->toBe(1);
});

test('dispatch Dec decrements', function (): void {
    $app = new AppRuntime(new CounterModel(5), 'counterUpdate');

    $app->dispatch(CounterMsg::Dec);

    expect($app->model()->count)->toBe(4);
});

test('dispatch Inc twice', function (): void {
    $app = new AppRuntime(new CounterModel(0), 'counterUpdate');

    $app->dispatch(CounterMsg::Inc);
    $app->dispatch(CounterMsg::Inc);
    $app->dispatch(CounterMsg::Inc);

    expect($app->model()->count)->toBe(3);
});

test('dispatch Reset', function (): void {
    $app = new AppRuntime(new CounterModel(99), 'counterUpdate');

    $app->dispatch(CounterMsg::Reset);

    expect($app->model()->count)->toBe(0);
});

test('dispatch sequence Inc Dec Reset', function (): void {
    $app = new AppRuntime(new CounterModel(10), 'counterUpdate');

    $app->dispatch(CounterMsg::Inc);    // 11
    $app->dispatch(CounterMsg::Inc);    // 12
    $app->dispatch(CounterMsg::Dec);    // 11
    $app->dispatch(CounterMsg::Reset);  // 0
    $app->dispatch(CounterMsg::Inc);    // 1

    expect($app->model()->count)->toBe(1);
});

test('dispatch returns the new model', function (): void {
    $app = new AppRuntime(new CounterModel(0), 'counterUpdate');

    $m1 = $app->dispatch(CounterMsg::Inc);
    expect($m1->count)->toBe(1);
    expect($app->model())->toBe($m1);

    $m2 = $app->dispatch(CounterMsg::Inc);
    expect($m2->count)->toBe(2);
    expect($app->model())->toBe($m2);
});

test('model() returns current model after dispatch', function (): void {
    $app = new AppRuntime(new CounterModel(7), 'counterUpdate');

    $app->dispatch(CounterMsg::Inc);

    expect($app->model()->count)->toBe(8);
    expect($app->model())->toEqual(new CounterModel(8));
});

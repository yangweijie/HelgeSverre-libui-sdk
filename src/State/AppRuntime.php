<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\State;

use Yangweijie\Ui2\EmitsEvents;

/**
 * Minimal Elm-architecture runtime — owns a Model and dispatches Messages.
 *
 * ```
 * $app = new AppRuntime(new CounterModel(0), fn(CounterModel $m, CounterMsg $msg) =>
 *     match ($msg) {
 *         CounterMsg::Inc => UpdateResult::pure(new CounterModel($m->count + 1)),
 *         CounterMsg::Dec => UpdateResult::pure(new CounterModel($m->count - 1)),
 *     },
 * );
 *
 * $surface->onClick('inc', function () use ($app, $labelLeaf, $surface) {
 *     $m = $app->dispatch(CounterMsg::Inc);
 *     $labelLeaf->spec = new LabelSpec("Count: {$m->count}", size: 16.0);
 *     $surface->redraw();
 * });
 * ```
 *
 * @template TModel of Model
 */
class AppRuntime
{
    use EmitsEvents;

    /** @var TModel */
    private Model $model;

    /** @var callable(TModel, Msg): UpdateResult */
    private mixed $update;

    /**
     * @param TModel                       $initial
     * @param callable(TModel, Msg): UpdateResult $update  Pure function: (Model, Msg) → UpdateResult
     */
    public function __construct(
        Model $initial,
        callable $update,
    ) {
        $this->model = $initial;
        $this->update = $update;
    }

    /**
     * Return the current model.
     *
     * @return TModel
     */
    public function model(): Model
    {
        return $this->model;
    }

    /**
     * Dispatch a Msg — runs the Update function, stores the new Model, returns it.
     *
     * @param  Msg     $msg
     * @return TModel  The new Model after the update.
     */
    public function dispatch(Msg $msg): Model
    {
        $prev = $this->model;
        $result = ($this->update)($this->model, $msg);
        $this->model = $result->model;

        // Observability: broadcast the transition so automation / debug observers
        // can tap state changes without touching business code (see design §4.5).
        $this->emit('state.changed', new StateChangedEvent($msg, $prev, $this->model));

        return $this->model;
    }

    /**
     * Export the current model as a plain array for the automation / observability
     * layer (e.g. served by the automation server's /state endpoint).
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return self::modelSnapshot($this->model);
    }

    /**
     * Reflect a readonly Model into an associative array.
     *
     * @return array<string, mixed>
     */
    public static function modelSnapshot(Model $model): array
    {
        $out = [];
        $ref = new \ReflectionObject($model);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) {
                continue;
            }
            $out[$prop->getName()] = $prop->getValue($model);
        }

        return $out;
    }
}

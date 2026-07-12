<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\State;

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
        $result = ($this->update)($this->model, $msg);
        $this->model = $result->model;

        return $this->model;
    }
}

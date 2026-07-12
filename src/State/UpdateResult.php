<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\State;

/**
 * The return value of an Update function — new Model + optional Effects.
 *
 * @template TModel of Model
 *
 * @psalm-immutable
 */
class UpdateResult
{
    /** @var list<Effect> */
    public readonly array $effects;

    /**
     * @param TModel       $model   The next application state.
     * @param list<Effect> $effects Side-effects to execute after the update.
     */
    public function __construct(
        public readonly Model $model,
        array $effects = [],
    ) {
        $this->effects = array_values($effects);
    }

    /**
     * Convenience: new Model with no effects.
     *
     * @param TModel $model
     */
    public static function pure(Model $model): self
    {
        return new self($model);
    }
}

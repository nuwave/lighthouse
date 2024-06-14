<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedOneToOne implements ArgResolver
{
    public function __construct(
        protected string $relationName,
    ) {}

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): void
    {
        $relation = $model->{$this->relationName}();
        assert($relation instanceof HasOne || $relation instanceof MorphOne);

        if ($args->has('create')) {
            $saveModel = new ResolveNested(new SaveModel());

            $saveModel($relation->make(), $args->arguments['create']->value);
        }

        if ($args->has('update')) {
            $updateModel = new ResolveNested(new UpdateModel(new SaveModel()));

            $updateModel($relation->make(), $args->arguments['update']->value);
        }

        if ($args->has('upsert')) {
            $upsertModel = new ResolveNested(new UpsertModel(new SaveModel()));

            $upsertModel($relation->make(), $args->arguments['upsert']->value);
        }

        if ($args->has('delete')) {
            $relation->getRelated()::destroy(
                $args->arguments['delete']->toPlain(),
            );
        }
    }
}

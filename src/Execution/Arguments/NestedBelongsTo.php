<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedBelongsTo implements ArgResolver
{
    /**
     * @var string
     */
    private $relationName;

    public function __construct(string $relationName)
    {
        $this->relationName = $relationName;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     * @return void
     */
    public function __invoke($parent, $args): void
    {
        /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo $relation */
        $relation = $parent->{$this->relationName}();

        if (isset($args->arguments['create'])) {
            $saveModel = new ResolveNested(new SaveModel($relation));

            $related = $saveModel(
                $relation->make(),
                $args->arguments['create']->value
            );
            $relation->associate($related);
        }

        if (isset($args->arguments['connect'])) {
            $relation->associate($args->arguments['connect']->value);
        }

        if (isset($args->arguments['update'])) {
            $updateModel = new ResolveNested(new UpdateModel(new SaveModel($relation)));

            $related = $updateModel(
                $relation->make(),
                $args->arguments['update']->value
            );
            $relation->associate($related);
        }

        if (isset($args->arguments['upsert'])) {
            $upsertModel = new ResolveNested(new UpsertModel(new SaveModel($relation)));

            $related = $upsertModel(
                $relation->make(),
                $args->arguments['upsert']->value
            );
            $relation->associate($related);
        }

        self::disconnectOrDelete($relation, $args);
    }

    public static function disconnectOrDelete(BelongsTo $relation, ArgumentSet $args): void
    {
        // We proceed with disconnecting/deleting only if the given $values is truthy.
        // There is no other information to be passed when issuing those operations,
        // but GraphQL forces us to pass some value. It would be unintuitive for
        // the end user if the given value had no effect on the execution.
        if (
            isset($args->arguments['disconnect'])
            && $args->arguments['disconnect']->value
        ) {
            $relation->dissociate();
        }

        if (
            isset($args->arguments['delete'])
            && $args->arguments['delete']->value
        ) {
            $relation->dissociate();
            $relation->delete();
        }
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedBelongsTo implements ArgResolver
{
    public function __construct(
        /**
         * @var \Illuminate\Database\Eloquent\Relations\BelongsTo<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model> $relation
         */
        protected BelongsTo $relation,
    ) {}

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  ArgumentSet  $args
     */
    public function __invoke($parent, $args): void
    {
        if ($args->has('create')) {
            $saveModel = new ResolveNested(new SaveModel($this->relation));
            $related = $saveModel(
                $this->relation->make(),
                $args->arguments['create']->value,
            );
            $this->relation->associate($related);
        }

        if ($args->has('connect')) {
            $this->relation->associate($args->arguments['connect']->value);
        }

        if ($args->has('update')) {
            $updateModel = new ResolveNested(new UpdateModel(new SaveModel($this->relation)));
            $related = $updateModel(
                $this->relation->make(),
                $args->arguments['update']->value,
            );
            $this->relation->associate($related);
        }

        if ($args->has('upsert')) {
            $upsertModel = new ResolveNested(new UpsertModel(new SaveModel($this->relation)));
            $related = $upsertModel(
                $this->relation->make(),
                $args->arguments['upsert']->value,
            );
            $this->relation->associate($related);
        }

        self::disconnectOrDelete($this->relation, $args);
    }

    /** @param  \Illuminate\Database\Eloquent\Relations\BelongsTo<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $relation */
    public static function disconnectOrDelete(BelongsTo $relation, ArgumentSet $args): void
    {
        // We proceed with disconnecting/deleting only if the given $values is truthy.
        // There is no other information to be passed when issuing those operations,
        // but GraphQL forces us to pass some value. It would be unintuitive for
        // the end user if the given value had no effect on the execution.
        if (
            $args->has('disconnect')
            && $args->arguments['disconnect']->value
        ) {
            $relation->dissociate();
        }

        if (
            $args->has('delete')
            && $args->arguments['delete']->value
        ) {
            $relation->dissociate();
            $relation->delete();
        }
    }
}

<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Support\Collection;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Resolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NestedBelongsTo implements Resolver
{
    /**
     * @var string
     */
    private $relationName;

    public function __construct(string $relationName)
    {
        $this->relationName = $relationName;
    }

    public function __invoke($model, $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo $relation */
        $relation = $model->{$this->relationName}();

        if (isset($args['create'])) {
            $belongsToModel = self::executeCreate(
                $relation->make(),
                new Collection($args['create'])
            );
            $relation->associate($belongsToModel);
        }

        if (isset($args['connect'])) {
            $relation->associate($args['connect']);
        }

        if (isset($args['update'])) {
            $belongsToModel = self::executeUpdate(
                $relation->getModel()->newInstance(),
                new Collection($args['update'])
            );
            $relation->associate($belongsToModel);
        }

        // We proceed with disconnecting/deleting only if the given $values is truthy.
        // There is no other information to be passed when issuing those operations,
        // but GraphQL forces us to pass some value. It would be unintuitive for
        // the end user if the given value had no effect on the execution.
        if ($args['disconnect'] ?? false) {
            $relation->dissociate();
        }

        if ($args['delete'] ?? false) {
            $relation->delete();
        }
    }
}

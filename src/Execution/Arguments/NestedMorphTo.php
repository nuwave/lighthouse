<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Schema\Context;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Resolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NestedMorphTo implements Resolver
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
        /** @var \Illuminate\Database\Eloquent\Relations\MorphTo $relation */
        $relation = $model->{$this->relationName}();

        // TODO implement create and update once we figure out how to do polymorphic input types https://github.com/nuwave/lighthouse/issues/900

        if (isset($args['connect'])) {
            $connectArgs = $args['connect'];

            $morphToModel = $relation->createModelByType(
                (string) $connectArgs['type']
            );
            $morphToModel->setAttribute(
                $morphToModel->getKeyName(),
                $connectArgs['id']
            );

            $relation->associate($morphToModel);
        }

        // We proceed with disconnecting/deleting only if the given $values is truthy.
        // There is no other information to be passed when issuing those operations,
        // but GraphQL forces us to pass some value. It would be unintuitive for
        // the end user if the given value had no effect on the execution.
        if ($nestedOperations['disconnect'] ?? false) {
            $relation->dissociate();
        }

        if ($nestedOperations['delete'] ?? false) {
            $relation->delete();
        }
    }
}

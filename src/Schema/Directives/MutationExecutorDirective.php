<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Execution\TransactionalMutations;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

abstract class MutationExecutorDirective extends BaseDirective implements FieldResolver, ArgResolver
{
    /**
     * @var \Nuwave\Lighthouse\Execution\TransactionalMutations
     */
    protected $transactionalMutations;

    public function __construct(TransactionalMutations $transactionalMutations)
    {
        $this->transactionalMutations = $transactionalMutations;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Model {
            $modelClass = $this->getModelClass();
            $model = new $modelClass();

            return $this->transactionalMutations->execute(
                function () use ($model, $resolveInfo): Model {
                    $mutated = $this->executeMutation($model, $resolveInfo->argumentSet);
                    assert($mutated instanceof Model);

                    return $mutated->refresh();
                },
                $model->getConnectionName()
            );
        });

        return $fieldValue;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    public function __invoke($parent, $args)
    {
        $relationName = $this->directiveArgValue(
            'relation',
            // Use the name of the argument if no explicit relation name is given
            $this->nodeName()
        );

        $relation = $parent->{$relationName}();
        assert($relation instanceof Relation);

        // @phpstan-ignore-next-line Relation&Builder mixin not recognized
        $related = $relation->make();
        assert($related instanceof Model);

        return $this->executeMutation($related, $args, $relation);
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    protected function executeMutation(Model $model, $args, ?Relation $parentRelation = null)
    {
        $update = new ResolveNested($this->makeExecutionFunction($parentRelation));

        return Utils::mapEach(
            static function (ArgumentSet $argumentSet) use ($update, $model) {
                return $update($model->newInstance(), $argumentSet);
            },
            $args
        );
    }

    /**
     * Prepare the execution function for a mutation on a model.
     */
    abstract protected function makeExecutionFunction(?Relation $parentRelation = null): callable;
}

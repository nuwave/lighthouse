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
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Model {
                $modelClass = $this->getModelClass();
                $model = new $modelClass();

                $executeMutation = function () use ($model, $resolveInfo): Model {
                    /** @var \Illuminate\Database\Eloquent\Model $mutated */
                    $mutated = $this->executeMutation(
                        $model,
                        $resolveInfo->argumentSet
                    );

                    return $mutated->refresh();
                };

                return $this->transactionalMutations->execute(
                    $executeMutation,
                    $model->getConnectionName()
                );
            }
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    public function __invoke($parent, $args)
    {
        $relationName = $this->directiveArgValue('relation')
            // Use the name of the argument if no explicit relation name is given
            ?? $this->nodeName();

        /** @var \Illuminate\Database\Eloquent\Relations\Relation $relation */
        $relation = $parent->{$relationName}();

        /** @var \Illuminate\Database\Eloquent\Model $related */
        // @phpstan-ignore-next-line Relation&Builder mixin not recognized
        $related = $relation->make();

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

        return Utils::applyEach(
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

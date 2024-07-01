<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Execution\TransactionalMutations;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

abstract class ManyMutationExecutorDirective extends BaseDirective implements FieldResolver, ArgResolver
{
    public const NOT_EXACTLY_ONE_ARGUMENT = '@*Many directives must ensure that clients pass exactly one field argument.';
    public const ARGUMENT_NOT_LIST = '@*Many directives must ensure that the single argument value is a list.';
    public const LIST_ITEM_NOT_INPUT_OBJECT = '@*Many directives must ensure that the list items of its single argument value are input objects.';

    public function __construct(
        protected TransactionalMutations $transactionalMutations,
    ) {}

    public function resolveField(FieldValue $fieldValue): callable
    {
        $modelClass = $this->getModelClass();

        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelClass): array {
            $model = new $modelClass();

            $arguments = $resolveInfo->argumentSet
                ->arguments;
            if (count($arguments) !== 1) {
                throw new DefinitionException(self::NOT_EXACTLY_ONE_ARGUMENT);
            }

            $argument = Arr::first($arguments);
            $inputs = $argument->value;
            if (! is_array($inputs)) {
                throw new DefinitionException(self::ARGUMENT_NOT_LIST);
            }

            return $this->transactionalMutations->execute(
                function () use ($model, $inputs): array {
                    $results = [];

                    foreach ($inputs as $input) {
                        if (! $input instanceof ArgumentSet) {
                            throw new DefinitionException(self::LIST_ITEM_NOT_INPUT_OBJECT);
                        }

                        $mutated = $this->executeMutation($model, $input);
                        assert($mutated instanceof Model);

                        $results[] = $mutated->refresh();
                    }

                    return $results;
                },
                $model->getConnectionName(),
            );
        };
    }

    /**
     * @param  Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    public function __invoke($model, $args): mixed
    {
        $relationName = $this->directiveArgValue(
            'relation',
            // Use the name of the argument if no explicit relation name is given
            $this->nodeName(),
        );

        $relation = $model->{$relationName}();
        assert($relation instanceof Relation);

        // @phpstan-ignore-next-line Relation&Builder mixin not recognized
        $related = $relation->make();
        assert($related instanceof Model);

        return $this->executeMutation($related, $args, $relation);
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $parentRelation
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    protected function executeMutation(Model $model, ArgumentSet|array $args, ?Relation $parentRelation = null): Model|array
    {
        $update = new ResolveNested($this->makeExecutionFunction($parentRelation));

        return Utils::mapEach(
            static fn (ArgumentSet $argumentSet): mixed => $update($model->newInstance(), $argumentSet),
            $args,
        );
    }

    /**
     * Prepare the execution function for a mutation on a model.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $parentRelation
     */
    abstract protected function makeExecutionFunction(?Relation $parentRelation = null): callable;
}

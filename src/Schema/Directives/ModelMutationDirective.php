<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Execution\TransactionalMutations;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Utils;

abstract class ModelMutationDirective extends BaseDirective implements FieldResolver, ArgResolver
{
    public function __construct(
        protected TransactionalMutations $transactionalMutations,
    ) {}

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

        $related = $relation->make(); // @phpstan-ignore method.notFound (Relation delegates to Builder)

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

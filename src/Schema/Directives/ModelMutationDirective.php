<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Execution\TransactionalMutations;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;
use Nuwave\Lighthouse\Support\Utils;

abstract class ModelMutationDirective extends BaseDirective implements FieldResolver, SaveAwareArgResolver
{
    public function __construct(
        protected TransactionalMutations $transactionalMutations,
    ) {}

    protected function relationName(): string
    {
        return $this->directiveArgValue(
            'relation',
            $this->nodeName(),
        );
    }

    public function runBeforeSave(Model $model): bool
    {
        return ArgPartitioner::methodReturnsRelation(
            new \ReflectionClass($model),
            $this->relationName(),
            BelongsTo::class,
        );
    }

    /**
     * @param  Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    public function __invoke($model, $args): mixed
    {
        $relation = $model->{$this->relationName()}();
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

<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\TypeNode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\ErrorPool;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Execution\TransactionalMutations;
use Nuwave\Lighthouse\GlobalId\GlobalId;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class ModifyModelExistenceDirective extends BaseDirective implements FieldResolver
{
    /**
     * @var \Nuwave\Lighthouse\GlobalId\GlobalId
     */
    protected $globalId;

    /**
     * @var \Nuwave\Lighthouse\Execution\ErrorPool
     */
    protected $errorPool;

    /**
     * @var \Nuwave\Lighthouse\Execution\TransactionalMutations
     */
    protected $transactionalMutations;

    public function __construct(GlobalId $globalId, ErrorPool $errorPool, TransactionalMutations $transactionalMutations)
    {
        $this->globalId = $globalId;
        $this->errorPool = $errorPool;
        $this->transactionalMutations = $transactionalMutations;
    }

    public static function couldNotModify(Model $model): Error
    {
        $modelClass = get_class($model);

        return new Error("Could not modify model {$modelClass} with ID {$model->getKey()}.");
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $expectsList = $this->expectsList($fieldValue->getField()->type);

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($expectsList) {
            $builder = $resolveInfo->enhanceBuilder(
                $this->getModelClass()::query(),
                $this->directiveArgValue('scopes', []),
                $root,
                $args,
                $context,
                $resolveInfo
            );
            assert($builder instanceof EloquentBuilder);

            $modelOrModels = $this->enhanceBuilder($builder)->get();

            foreach ($modelOrModels as $model) {
                $success = $this->transactionalMutations->execute(
                    fn (): bool => $this->modifyExistence($model),
                    $model->getConnectionName()
                );

                if (! $success) {
                    $this->errorPool->record(self::couldNotModify($model));
                }
            }

            return $expectsList
                ? $modelOrModels
                : $modelOrModels->first();
        });

        return $fieldValue;
    }

    private function expectsList(TypeNode $typeNode): bool
    {
        if ($typeNode instanceof NonNullTypeNode) {
            return $this->expectsList($typeNode->type);
        }

        return $typeNode instanceof ListTypeNode;
    }

    /**
     * Enhance the builder used to resolve the models.
     */
    abstract protected function enhanceBuilder(EloquentBuilder $builder): EloquentBuilder;

    /**
     * Bring a model in or out of existence.
     *
     * The return value indicates if the operation was successful.
     */
    abstract protected function modifyExistence(Model $model): bool;
}

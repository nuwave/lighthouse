<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\TypeNode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
    public function __construct(
        protected GlobalId $globalId,
        protected ErrorPool $errorPool,
        protected TransactionalMutations $transactionalMutations,
    ) {}

    public function resolveField(FieldValue $fieldValue): callable
    {
        $expectsList = $this->expectsList($fieldValue->getField()->type);
        $modelClass = $this->getModelClass();
        $scopes = $this->directiveArgValue('scopes', []);

        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelClass, $scopes, $expectsList): EloquentCollection|Model|null {
            $builder = $modelClass::query();

            if (! $resolveInfo->wouldEnhanceBuilder($builder, $scopes, $root, $args, $context, $resolveInfo)) {
                throw self::wouldModifyAll();
            }

            $builder = $resolveInfo->enhanceBuilder($builder, $scopes, $root, $args, $context, $resolveInfo);
            assert($builder instanceof EloquentBuilder);

            $modelOrModels = $this->enhanceBuilder($builder)->get();

            foreach ($modelOrModels as $model) {
                $success = $this->transactionalMutations->execute(
                    fn (): bool => $this->modifyExistence($model),
                    $model->getConnectionName(),
                );

                if (! $success) {
                    $this->errorPool->record(self::couldNotModify($model));
                }
            }

            return $expectsList
                ? $modelOrModels
                : $modelOrModels->first();
        };
    }

    public static function couldNotModify(Model $model): Error
    {
        $modelClass = $model::class;

        return new Error("Could not modify model {$modelClass} with ID {$model->getKey()}.");
    }

    public static function wouldModifyAll(): Error
    {
        return new Error('Would modify all models, use an argument to filter.');
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
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $builder
     *
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     */
    abstract protected function enhanceBuilder(EloquentBuilder $builder): EloquentBuilder;

    /**
     * Bring a model in or out of existence.
     *
     * The return value indicates if the operation was successful.
     */
    abstract protected function modifyExistence(Model $model): bool;
}

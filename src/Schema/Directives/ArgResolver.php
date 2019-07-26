<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\DatabaseManager;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\MutationExecutor;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedAfter;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedBefore;

class ArgResolver extends BaseDirective implements FieldResolver
{
    /**
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $databaseManager;

    /**
     * @param  \Illuminate\Database\DatabaseManager  $databaseManager
     * @return void
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'create';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Model {
                [$before, $regular, $after] = $this->partitionResolverInputs($args, $resolveInfo->fieldDefinition);

                $modelClassName = $this->getModelClass();

                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = new $modelClassName($regular);

                /** @var ResolveNestedBefore $afterResolver */
                foreach ($before as $afterResolver) {
                    $afterResolver->resolveBefore($model, $args, $context);
                }

                $model->save();

                /** @var ResolveNestedAfter $afterResolver */
                foreach ($after as $afterResolver) {
                    $afterResolver->resolveAfter($model, $args, $context);
                }
                $executeMutation = function () use ($model, $args): Model {
                    return MutationExecutor::executeCreate($model, new Collection($args))->refresh();
                };

                return config('lighthouse.transactional_mutations', true)
                    ? $this->databaseManager->connection($model->getConnectionName())->transaction($executeMutation)
                    : $executeMutation();
            }
        );
    }

    public static function defaultArgResolver($root, $value)
    {
        $root->value;
    }
}

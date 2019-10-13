<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\DatabaseManager;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

abstract class MutationExecutorDirective extends BaseDirective implements FieldResolver, DefinedDirective
{
    /**
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $databaseManager;

    /**
     * The GlobalId resolver.
     *
     * @var \Nuwave\Lighthouse\Support\Contracts\GlobalId
     */
    protected $globalId;

    /**
     * UpdateDirective constructor.
     *
     * @param  \Illuminate\Database\DatabaseManager  $databaseManager
     * @param  \Nuwave\Lighthouse\Support\Contracts\GlobalId  $globalId
     * @return void
     */
    public function __construct(DatabaseManager $databaseManager, GlobalId $globalId)
    {
        $this->databaseManager = $databaseManager;
        $this->globalId = $globalId;
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
                $modelClass = $this->getModelClass();
                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = new $modelClass;

                $executeMutation = function () use ($model, $args): Model {
                    return $this->executeMutation($model, new Collection($args))->refresh();
                };

                return config('lighthouse.transactional_mutations', true)
                    ? $this->databaseManager->connection($model->getConnectionName())->transaction($executeMutation)
                    : $executeMutation();
            }
        );
    }

    abstract protected function executeMutation(Model $model, Collection $args, ?Relation $parentRelation = null): Model;
}

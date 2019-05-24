<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\DatabaseManager;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\MutationExecutor;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class CreateDirective extends BaseDirective implements FieldResolver
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
            function ($root, array $args): Model {
                $modelClassName = $this->getModelClass();
                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = new $modelClassName;

                /*
                 * @deprecated in favour of @spread
                 */
                if ($this->directiveArgValue('flatten', false)) {
                    $args = reset($args);
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
}

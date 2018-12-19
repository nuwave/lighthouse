<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\DatabaseManager;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\MutationExecutor;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class CreateDirective extends BaseDirective implements FieldResolver
{
    /**
     * The policy mappings for the application.
     *
     * @var DatabaseManager
     */
    private $db;

    public function __construct(DatabaseManager $database)
    {
        $this->db = $database;
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
     * @param FieldValue $fieldValue
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args) {
                $modelClassName = $this->getModelClass();
                /** @var Model $model */
                $model = new $modelClassName();

                $flatten = $this->directiveArgValue('flatten', false);
                $args = $flatten
                    ? reset($args)
                    : $args;


                if (! config('lighthouse.transactional_mutations', true)) {
                    return MutationExecutor::executeCreate($model, collect($args))->refresh();
                }


                return $this->db->connection()->transaction(function () use ($model, $args) {
                    return MutationExecutor::executeCreate($model, collect($args))->refresh();
                });
            }
        );
    }
}

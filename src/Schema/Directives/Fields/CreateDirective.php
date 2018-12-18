<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\MutationExecutor;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Illuminate\Database\DatabaseManager;
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

                $this->db->connection()->beginTransaction();
                $mutEx = MutationExecutor::executeCreate($model, collect($args));
                $this->db->connection()->commit();
                return $mutEx;
            }
        );
    }
}

<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Database\QueryFilter;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

/**
 * Trait HandlesQueries.
 *
 * @deprecated
 */
trait HandlesQueries
{
    /**
     * @param FieldValue $value
     *
     * @throws DirectiveException
     *
     * @return mixed|string
     */
    public function getModelClass(FieldValue $value)
    {
        $model = DirectiveUtil::directiveArgValue(
            DirectiveUtil::fieldDirective($value->getField(), $this->name()),
            'model'
        );

        if (! $model) {
            $message = sprintf(
                'A `model` argument must be assigned to the %s directive on the %s field [%s]',
                $this->name(),
                $value->getNodeName(),
                $value->getFieldName()
            );

            throw new DirectiveException($message);
        }

        if (! class_exists($model)) {
            $model = config('lighthouse.namespaces.models').'\\'.$model;
        }

        return $model;
    }

    /**
     * Get scope(s) to run on connection.
     *
     * @param FieldValue $value
     *
     * @return array
     */
    protected function getScopes(FieldValue $value)
    {
        return DirectiveUtil::directiveArgValue(
            DirectiveUtil::fieldDirective($value->getField(), $this->name()),
            'scopes',
            []
        );
    }

    public function applyFilters($query, $args)
    {
        return $query->when(isset($args['query.filter']), function ($q) use ($args) {
            return QueryFilter::build($q, $args);
        });
    }

    public function applyScopes($query, $args, FieldValue $value)
    {
        foreach ($this->getScopes($value) as $scope) {
            call_user_func_array([$query, $scope], [$args]);
        }

        return $query;
    }
}

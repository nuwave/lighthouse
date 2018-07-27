<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Execution\QueryUtils;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class AllDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'all';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        return $value->setResolver(function ($root, $args){
            $modelClass = $this->getModelClass();
            $query = QueryUtils::applyFilters($modelClass::query(), $args);
            $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));

            return $query->get();
        });
    }
}

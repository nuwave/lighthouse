<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Execution\QueryUtils;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class FirstDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'first';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     * @throws \Nuwave\Lighthouse\Support\Exceptions\DirectiveException
     */
    public function resolveField(FieldValue $value)
    {
        $model = $this->getModelClass();

        return $value->setResolver(function ($root, $args) use ($model) {
            $query = QueryUtils::applyFilters($model::query(), $args);
            $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));
            return $query->first();
        });
    }
}

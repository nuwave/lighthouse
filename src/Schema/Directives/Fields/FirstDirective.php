<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueries;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class FirstDirective implements FieldResolver
{
    use HandlesQueries, HandlesDirectives, HandlesQueryFilter;

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
        $model = $this->getModelClass($value);

        return $value->setResolver(function ($root, $args) use ($model, $value) {
            $query = $this->applyFilters($model::query(), $args);
            $query = $this->applyScopes($query, $args, $value);
            return $query->first();
        });
    }
}

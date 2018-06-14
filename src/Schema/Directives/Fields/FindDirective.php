<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueries;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class FindDirective extends AbstractFieldDirective implements FieldResolver
{
    use HandlesQueries, HandlesDirectives, HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'find';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @throws DirectiveException
     *
     * @return \Closure
     */
    public function resolveField(FieldValue $value)
    {
        $model = $this->getModelClass($value);

        return function ($root, $args) use ($model, $value) {
            /** @var Builder $query */
            $query = $this->applyFilters($model::query(), $args);
            $query = $this->applyScopes($query, $args, $value);
            $total = $query->count();
            if ($total > 1) {
                throw new Error('Query returned more than one result.');
            }

            return $query->first();
        };
    }
}

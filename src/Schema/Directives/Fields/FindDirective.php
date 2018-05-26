<?php


namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandleQueries;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class FindDirective implements FieldResolver
{
    use HandleQueries, HandlesDirectives, HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'find';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     * @throws DirectiveException
     */
    public function resolveField(FieldValue $value)
    {
        $model = $this->getModelClass($value);

        return $value->setResolver(function ($root, $args) use ($model, $value) {
            /** @var Builder $query */
            $query = $this->applyFilters($model::query(), $args);
            $query = $this->applyScopes($query, $args, $value);
            $total = $query->count();
            if($total > 1) {
                throw new Error('Query returned more than one result.');
            }
            return $query->first();
        });
    }
}
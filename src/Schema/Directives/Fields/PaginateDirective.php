<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Pagination\Paginator;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Database\QueryFilter;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandleQueries;
use Nuwave\Lighthouse\Support\Traits\CreatesPaginators;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class PaginateDirective implements FieldResolver
{
    use CreatesPaginators, HandlesGlobalId, HandlesQueryFilter, HandleQueries;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'paginate';
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
        $type = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), $this->name()),
            'type',
            'paginator'
        );

        $model = $this->getModelClass($value);

        $resolver = in_array($type, ['relay', 'connection'])
            ? $this->connectionTypeResolver($value, $model)
            : $this->paginatorTypeResolver($value, $model);

        return $value->setResolver($resolver);
    }

    /**
     * Create a paginator resolver.
     *
     * @param FieldValue $value
     * @param string     $model
     *
     * @return \Closure
     */
    protected function paginatorTypeResolver(FieldValue $value, $model)
    {
        $this->registerPaginator($value);

        return function ($root, array $args) use ($model, $value) {
            $first = data_get($args, 'count', 15);
            $page = data_get($args, 'page', 1);

            $query = $this->applyFilters($model::query(), $args);
            $query = $this->applyScopes($query, $args, $value);

            Paginator::currentPageResolver(function() use ($page) {
                return $page;
            });
            return $query->paginate($first);
        };
    }

    /**
     * Create a connection resolver.
     *
     * @param FieldValue $value
     * @param string     $model
     *
     * @return \Closure
     */
    protected function connectionTypeResolver(FieldValue $value, $model)
    {
        $this->registerConnection($value);

        return function ($root, array $args) use ($model, $value) {
            $first = data_get($args, 'first', 15);
            $after = $this->decodeCursor($args);
            $page = $first && $after ? floor(($first + $after) / $first) : 1;

            $query = $this->applyFilters($model::query(), $args);
            $query = $this->applyScopes($query, $args, $value);

            Paginator::currentPageResolver(function() use ($page) {
                return $page;
            });
            return $query->paginate($first);
        };
    }
}

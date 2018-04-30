<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Pagination\Paginator;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Database\QueryFilter;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\CreatesPaginators;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class PaginateDirective implements FieldResolver
{
    use CreatesPaginators, HandlesGlobalId;

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
     */
    public function resolveField(FieldValue $value)
    {
        $type = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), $this->name()),
            'type',
            'paginator'
        );

        $model = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), $this->name()),
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
        $scopes = $this->getScopes($value);

        return function ($root, array $args) use ($model, $scopes) {
            $first = data_get($args, 'count', 15);
            $page = data_get($args, 'page', 1);
            $query = $model::query()->when(isset($args['query.filter']), function ($q) use ($args) {
                return QueryFilter::build($q, $args);
            });

            foreach ($scopes as $scope) {
                call_user_func_array([$query, $scope], [$args]);
            }

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
        $scopes = $this->getScopes($value);

        return function ($root, array $args) use ($model, $scopes) {
            $first = data_get($args, 'first', 15);
            $after = $this->decodeCursor($args);
            $page = $first && $after ? floor(($first + $after) / $first) : 1;
            $query = $model::query()->when(isset($args['query.filter']), function ($q) use ($args) {
                return QueryFilter::build($q, $args);
            });

            foreach ($scopes as $scope) {
                call_user_func_array([$query, $scope], [$args]);
            }

            Paginator::currentPageResolver(function() use ($page) {
                return $page;
            });
            return $query->paginate($first);
        };
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
        return $this->directiveArgValue(
            $this->fieldDirective($value->getField(), $this->name()),
            'scopes',
            []
        );
    }
}

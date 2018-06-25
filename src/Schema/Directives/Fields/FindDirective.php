<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Execution\QueryUtils;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class FindDirective extends BaseDirective implements FieldResolver
{
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
        $model = $this->getModelClass();

        return $value->setResolver(function ($root, $args) use ($model) {
            /** @var Builder $query */
            $query = QueryUtils::applyFilters($model::query(), $args);
            $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));
            $total = $query->count();

            if ($total > 1) {
                throw new Error('Query returned more than one result.');
            }
            return $query->first();
        });
    }
}

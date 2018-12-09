<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Execution\QueryUtils;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class FindDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'find';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $fieldValue
     *
     * @throws DirectiveException
     * @throws DefinitionException
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $model = $this->getModelClass();

        return $fieldValue->setResolver(
            function ($root, array $args) use ($model) {
                /** @var Builder $query */
                $query = QueryUtils::applyFilters($model::query(), $args);
                $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));
                $total = $query->count();

                if ($total > 1) {
                    throw new Error('Query returned more than one result.');
                }
                return $query->first();
            }
        );
    }
}

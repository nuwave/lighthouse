<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\QueryUtils;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class AllDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'all';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $fieldValue
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, $args){
                /** @var Model $modelClass */
                $modelClass = $this->getModelClass();

                $query = QueryUtils::applyFilters($modelClass::query(), $args);
                $query = QueryUtils::applyScopes($query, $args, $this->directiveArgValue('scopes', []));

                return $query->get();
            }
        );
    }
}

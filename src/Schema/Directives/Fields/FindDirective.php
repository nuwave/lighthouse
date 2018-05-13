<?php


namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\CanUseModels;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class FindDirective implements FieldResolver
{
    use CanUseModels, HandlesDirectives, HandlesQueryFilter;

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

        return $value->setResolver(function ($root, $args) use ($model) {
            /** @var Builder $query */
            $query = $this->applyFiltersOnQuery($model::query(), $args);
            $total = $query->count();
            if($total > 1) {
                throw new Error('Query returned more than one result.');
            }
            return $query->first();
        });
    }
}
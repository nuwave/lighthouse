<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
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
     * @return FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $model = $this->getModelClass();

        return $fieldValue->setResolver(
            function ($root, array $args, $context, ResolveInfo $resolveInfo) use ($model) {
                /** @var Builder $query */
                $query = QueryFilter::apply(
                    $model::query(),
                    $args,
                    $this->directiveArgValue('scopes', []),
                    $resolveInfo
                );
                $total = $query->count();

                if ($total > 1) {
                    throw new Error('Query returned more than one result.');
                }

                return $query->first();
            }
        );
    }
}

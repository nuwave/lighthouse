<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

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
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $model = $this->getModelClass();

        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($model) {
                /** @var \Illuminate\Database\Eloquent\Builder $query */
                $query = QueryFilter::apply(
                    $model::query(),
                    $args,
                    $this->directiveArgValue('scopes', []),
                    $resolveInfo
                );

                if ($query->count() > 1) {
                    throw new Error('The query returned more than one result.');
                }

                return $query->first();
            }
        );
    }
}

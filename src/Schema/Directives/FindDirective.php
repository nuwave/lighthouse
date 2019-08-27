<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

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
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = $this->getModelClass();

        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($model): ?Model {
                $query = $resolveInfo
                    ->builder
                    ->addScopes(
                        $this->directiveArgValue('scopes', [])
                    )
                    ->apply(
                        $model::query(),
                        $args
                    );

                Utils::applyTrashedModificationIfNeeded($resolveInfo, $args, $query);

                $results = $query->get();

                if ($results->count() > 1) {
                    throw new Error('The query returned more than one result.');
                }

                return $results->first();
            }
        );
    }
}

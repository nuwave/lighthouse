<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

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
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                /** @var \Illuminate\Database\Eloquent\Model $modelClass */
                $modelClass = $this->getModelClass();

                return $resolveInfo
                    ->builder
                    ->addScopes(
                        $this->directiveArgValue('scopes', [])
                    )
                    ->apply(
                        $modelClass::query(),
                        $args
                    )
                    ->get();
            }
        );
    }
}

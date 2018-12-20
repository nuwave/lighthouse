<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class FirstDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'first';
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
            function ($root, array $args, $context, ResolveInfo $resolveInfo) use ($model) {
                /** @var Builder $query */
                $query = QueryFilter::apply(
                    $model::query(),
                    $args,
                    $this->directiveArgValue('scopes', []),
                    $resolveInfo
                );

                return $query->first();
            }
        );
    }
}

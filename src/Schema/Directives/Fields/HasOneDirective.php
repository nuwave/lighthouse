<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\DataLoader\Loaders\HasOneLoader;

class HasOneDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'hasOne';
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
        return $value->setResolver(
            function (Model $parent, array $resolveArgs, $context = null, ResolveInfo $resolveInfo = null) {
                /** @var HasOneLoader $hasOneLoader */
                $hasOneLoader = graphql()->batchLoader(
                    HasOneLoader::class,
                    $resolveInfo->path,
                    [
                        'relation' => $this->directiveArgValue('relation', $this->definitionNode->name->value),
                        'resolveArgs' => $resolveArgs,
                        'scopes' => $this->directiveArgValue('scopes', []),
                    ]
                );

                return $hasOneLoader->load($parent->getKey(), ['parent' => $parent]);
            }
        );
    }
}

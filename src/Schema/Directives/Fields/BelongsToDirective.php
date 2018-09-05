<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\DataLoader\Loaders\BelongsToLoader;

class BelongsToDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'belongsTo';
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
        return $value->setResolver(function (Model $parent, array $args, $context = null, ResolveInfo $resolveInfo){
            $relation = $this->directiveArgValue('relation', $this->definitionNode->name->value);

            /** @var BelongsToLoader $belongsToLoader */
            $belongsToLoader = graphql()->batchLoader(
                BelongsToLoader::class,
                $resolveInfo->path,
                ['relation' => $relation, 'resolveArgs' => $args]
            );

            return $belongsToLoader->load($parent->getKey(), [
                'parent' => $parent
            ]);
        });
    }
}

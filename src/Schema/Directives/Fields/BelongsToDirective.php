<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\DataLoader\Loaders\BelongsToLoader;

class BelongsToDirective extends BaseFieldDirective implements FieldResolver
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
        $relation = $this->associatedArgValue('relation', $value->getFieldName());

        return $value->setResolver(function ($root, array $args, $context = null, $info = null) use ($relation) {
            return graphql()->batch(BelongsToLoader::class, $root->getKey(), [
                'relation' => $relation,
                'root' => $root,
                'args' => $args,
            ], BelongsToLoader::key($root, $relation, $info));
        });
    }
}

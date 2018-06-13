<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\DataLoader\Loaders\BelongsToLoader;

class BelongsToDirective extends AbstractFieldDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'belongsTo';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    public function resolveField(FieldValue $value)
    {
        $relation = $this->associatedArgValue('relation', $this->fieldDefinition->name->value);

        return function ($root, array $args, $context = null, $info = null) use ($relation) {
            return graphql()->batch(BelongsToLoader::class, $root->getKey(), [
                'relation' => $relation,
                'root' => $root,
                'args' => $args,
            ], BelongsToLoader::key($root, $relation, $info));
        };
    }
}

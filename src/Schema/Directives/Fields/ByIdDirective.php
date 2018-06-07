<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandleQueries;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class ByIdDirective implements FieldResolver
{
    use HandleQueries, HandlesDirectives, HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'byId';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        $model = $this->getModelClass($value);

        return $value->setResolver(function ($root, $args) use ($model, $value) {
            return $model::find($args['id']);
        });
    }
}

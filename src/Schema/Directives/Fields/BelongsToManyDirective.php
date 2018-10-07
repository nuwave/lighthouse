<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class BelongsToManyDirective extends MultipleRelationDirective implements FieldResolver, FieldManipulator
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name():string
    {
        return 'belongsToMany';
    }
}

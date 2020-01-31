<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class ModelClassDirective extends BaseDirective implements DefinedDirective, Directive
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Map a model class to an object type.
This can be used when the name of the model differs from the name of the type.

**This directive will be renamed to @model in v5.**
"""
directive @modelClass(
    """
    The class name of the corresponding model.
    """
    class: String!
) on OBJECT
SDL;
    }
}

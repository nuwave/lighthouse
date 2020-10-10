<?php

namespace Tests\Integration\Events;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class FieldDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
    """
    An alternate @field.
    """
    directive @field on FIELD_DEFINITION
GRAPHQL;
    }
}

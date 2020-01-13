<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class CacheKeyDirective extends BaseDirective implements Directive, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Specify the field to use as a key when creating a cache.
"""
directive @cacheKey on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }
}

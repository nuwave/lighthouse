<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class ModelDirective extends NodeDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Enable fetching an Eloquent model by its global id through the `node` query.

@deprecated(reason: "Use @node instead. This directive will be repurposed and do what @modelClass does now in v5.")
"""
directive @model on OBJECT
SDL;
    }
}

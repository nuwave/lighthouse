<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ExternalDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Individual federated services should be runnable without having the entire graph present. Fields marked with @external 
are declarations of fields that are defined in another service. All fields referred to in @key, @requires, and @provides
directives need to have corresponding @external fields in the same service.

https://www.apollographql.com/docs/apollo-server/federation/federation-spec/#schema-modifications-glossary
"""
directive @external on FIELD_DEFINITION
GRAPHQL;
    }
}

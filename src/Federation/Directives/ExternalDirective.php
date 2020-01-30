<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ExternalDirective extends BaseDirective
{
    /**
     * @return string
     * @see https://www.apollographql.com/docs/apollo-server/federation/federation-spec/#schema-modifications-glossary
     */
    public static function definition(): string
    {
        return /* @lang GraphQL */
            <<<'SDL'
"""
Individual federated services should be runnable without having the entire graph present. Fields marked with @external 
are declarations of fields that are defined in another service. All fields referred to in @key, @requires, and @provides
directives need to have corresponding @external fields in the same service.
"""
directive @external on FIELD_DEFINITION
SDL;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'external';
    }
}

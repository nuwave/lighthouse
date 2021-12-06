<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ExtendsDirective extends BaseDirective
{
    public const NAME = 'extends';

    /**
     * @see https://www.apollographql.com/docs/apollo-server/federation/federation-spec/#schema-modifications-glossary
     */
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Some libraries such as graphql-java don't have native support for type extensions in their printer. Apollo Federation
supports using an @extends directive in place of extend type to annotate type references:

    type User @key(fields: "id") @extends {

instead of:

    extend type User @key(fields: "id") {
"""
directive @extends on OBJECT | INTERFACE
GRAPHQL;
    }
}

<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ExtendsDirective extends BaseDirective
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
Some libraries such as graphql-java don't have native support for type extensions in their printer. Apollo Federation 
supports using an @extends directive in place of extend type to annotate type references:

    type User @key(fields: "id") @extends {

instead of:

    extend type User @key(fields: "id") {
"""
directive @extends on OBJECT | INTERFACE
SDL;
    }

    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'extends';
    }
}

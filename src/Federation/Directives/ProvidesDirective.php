<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ProvidesDirective extends BaseDirective
{
    /**
     * @see https://www.apollographql.com/docs/apollo-server/federation/federation-spec/#schema-modifications-glossary
     */
    public static function definition(): string
    {
        return /* @lang GraphQL */
            <<<'SDL'
"""
The @provides directive is used to annotate the expected returned fieldset from a field on a base type that is 
guaranteed to be selectable by the gateway. Given the following example:

    type Review @key(fields: "id") {
      product: Product @provides(fields: "name")
    }
    
    extend type Product @key(fields: "upc") {
      upc: String @external
      name: String @external
    }
"""
directive @provides(
    """
    Annotate the expected returned fieldset from a field on a base type that is guaranteed to be selectable by the 
    gateway.
    """
    fields: _FieldSet!
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Name of the directive.
     */
    public function name(): string
    {
        return 'provides';
    }
}

<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class RequiresDirective extends BaseDirective
{
    public const NAME = 'requires';

    /**
     * @see https://www.apollographql.com/docs/apollo-server/federation/federation-spec/#schema-modifications-glossary
     */
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
The @requires directive is used to annotate the required input fieldset from a base type for a resolver. It is used to
develop a query plan where the required fields may not be needed by the client, but the service may need additional
information from other services. For example:

    extend type User @key(fields: "id") {
      id: ID! @external
      email: String @external
      reviews: [Review] @requires(fields: "email")
    }
"""
directive @requires(
    """
    It is used to develop a query plan where the required fields may not be needed by the client, but the service may
    need additional information from other services.
    """
    fields: _FieldSet!
) on FIELD_DEFINITION
GRAPHQL;
    }
}

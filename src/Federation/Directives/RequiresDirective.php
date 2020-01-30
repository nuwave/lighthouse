<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class RequiresDirective extends BaseDirective
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
SDL;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'requires';
    }
}

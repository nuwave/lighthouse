<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class RequiresScopesDirective extends BaseDirective
{
    public const NAME = 'requiresScopes';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Indicates to composition that the target element is accessible only to authenticated users
with the appropriate JWT scopes.

```graphql
extend schema
    @link(url: "https://specs.apollo.dev/federation/v2.9", import: ["@requiresScopes"])

type User @key(fields: "id")
@requiresScopes(scopes: [["profile.read"], ["admin"]]) {
  id: ID!
  email: String!
}
```

https://www.apollographql.com/docs/graphos/schema-design/federated-schemas/reference/directives#requiresscopes
"""
directive @requiresScopes(scopes: [[federation__Scope!]!]!) on FIELD_DEFINITION | OBJECT | INTERFACE | SCALAR | ENUM

"""
A JWT scope required by `@requiresScopes`.
"""
scalar federation__Scope
GRAPHQL;
    }
}

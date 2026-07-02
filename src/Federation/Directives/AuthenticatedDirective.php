<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class AuthenticatedDirective extends BaseDirective
{
    public const NAME = 'authenticated';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Indicates to composition that the target element is accessible only to authenticated users.

```graphql
extend schema
    @link(url: "https://specs.apollo.dev/federation/v2.9", import: ["@authenticated"])

type User @key(fields: "id") @authenticated {
  id: ID!
  email: String!
}
```

https://www.apollographql.com/docs/graphos/schema-design/federated-schemas/reference/directives#authenticated
"""
directive @authenticated on FIELD_DEFINITION | OBJECT | INTERFACE | SCALAR | ENUM
GRAPHQL;
    }
}

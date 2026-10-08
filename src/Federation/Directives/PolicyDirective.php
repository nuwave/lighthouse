<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class PolicyDirective extends BaseDirective
{
    public const NAME = 'policy';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Indicates to composition that the target element is restricted based on authorization policies.

```graphql
extend schema
    @link(url: "https://specs.apollo.dev/federation/v2.9", import: ["@policy"])

type User @key(fields: "id")
@policy(policies: [["read:user"], ["admin"]]) {
  id: ID!
  email: String!
}
```

https://www.apollographql.com/docs/graphos/schema-design/federated-schemas/reference/directives#policy
"""
directive @policy(policies: [[federation__Policy!]!]!) on FIELD_DEFINITION | OBJECT | INTERFACE | SCALAR | ENUM

"""
An authorization policy value required by `@policy`.
"""
scalar federation__Policy
GRAPHQL;
    }
}

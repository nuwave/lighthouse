<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class CostDirective extends BaseDirective
{
    public const NAME = 'cost';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Assigns a weight used by the router when calculating operation cost.

```graphql
type User @cost(weight: 1) {
  id: ID!
  email: String!
}
```

https://www.apollographql.com/docs/graphos/schema-design/federated-schemas/reference/directives#cost
"""
directive @cost(weight: Int!) on ARGUMENT_DEFINITION | ENUM | FIELD_DEFINITION | INPUT_FIELD_DEFINITION | OBJECT | SCALAR
GRAPHQL;
    }
}

<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ShareableDirective extends BaseDirective
{
    public const NAME = 'shareable';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Indicates that an object type's field is allowed to be resolved by multiple subgraphs
(by default in Federation 2, object fields can be resolved by only one subgraph).
If applied to an object type definition, all of that type's fields are considered @shareable.

```graphql
type Position {
  x: Int! @shareable
  y: Int! @shareable
}

type Position @shareable {
  x: Int!
  y: Int!
}
```

If a field is marked @shareable in any subgraph, it must be marked as either
@shareable or @external in every Federation 2 subgraph that defines it.

If a field is included in an entity's @key directive, that field is automatically
considered @shareable and the directive is not required in the corresponding subgraph(s).

https://www.apollographql.com/docs/federation/federated-types/federated-directives#shareable
"""
directive @shareable on FIELD_DEFINITION | OBJECT
GRAPHQL;
    }
}

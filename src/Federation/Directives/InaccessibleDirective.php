<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class InaccessibleDirective extends BaseDirective
{
    public const NAME = 'inaccessible';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Indicates that a definition in the subgraph schema should be omitted from the router's API schema,
even if that definition is also present in other subgraphs.
This means that the field is not exposed to clients at all.

```graphql
type Position @shareable {
  x: Int!
  y: Int!
  z: Int! @inaccessible
}
```

An @inaccessible field or type is not omitted from the supergraph schema, so the router
still knows it exists (but clients can't include it in operations).
This is what enables the router to use an @inaccessible field as part of an entity's @key
when combining entity fields from multiple subgraphs.

If a type is marked @inaccessible, all fields that return that type must also be marked @inaccessible.
Otherwise, a composition error occurs.

https://www.apollographql.com/docs/federation/federated-types/federated-directives#inaccessible
"""
directive @inaccessible on FIELD_DEFINITION | INTERFACE | OBJECT | UNION | ARGUMENT_DEFINITION | SCALAR | ENUM | ENUM_VALUE | INPUT_OBJECT | INPUT_FIELD_DEFINITION
GRAPHQL;
    }
}

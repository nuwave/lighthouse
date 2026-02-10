<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class OverrideDirective extends BaseDirective
{
    public const NAME = 'override';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Indicates that an object field is now resolved by this subgraph instead of another subgraph
where it's also defined. This enables you to migrate a field from one subgraph to another.

You can apply @override to entity fields and fields of the root operation types (such as Query and Mutation).

```graphql
type Product @key(fields: "id") {
  id: ID!
  inStock: Boolean! @override(from: "Products")
}
```

You can apply @override to a @shareable field. If you do, only the subgraph you provide
in the from argument no longer resolves that field. Other subgraphs can still resolve the field.

Only one subgraph can @override any given field.
If multiple subgraphs attempt to @override the same field, a composition error occurs.

https://www.apollographql.com/docs/federation/federated-types/federated-directives#override
"""
directive @override(from: String!) on FIELD_DEFINITION
GRAPHQL;
    }
}

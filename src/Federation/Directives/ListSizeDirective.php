<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ListSizeDirective extends BaseDirective
{
    public const NAME = 'listSize';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Describes list cardinality for operation cost calculations in the router.

```graphql
type Query {
  items: [Item!] @listSize(assumedSize: 10)
}
type Item @key(fields: "id") {
  id: ID
}
```

https://www.apollographql.com/docs/graphos/schema-design/federated-schemas/reference/directives#listsize
"""
directive @listSize(
    assumedSize: Int
    slicingArguments: [String!]
    sizedFields: [String!]
    requireOneSlicingArgument: Boolean = true
) on FIELD_DEFINITION
GRAPHQL;
    }
}

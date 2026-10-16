<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ContextDirective extends BaseDirective
{
    public const NAME = 'context';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Names a context whose fields the router can pass to descendant field arguments.

```graphql
type A @key(fields: "id") @context(name: "userContext") {
  id: ID!
  prop: String!
}

type B @key(fields: "id") @context(name: "userContext") {
  id: ID!
  prop: String!
}

type U @key(fields: "id") {
  id: ID!
  field (arg: String @fromContext(field: "$userContext { prop }")): String!
}
```

https://www.apollographql.com/docs/graphos/schema-design/federated-schemas/reference/directives#context
"""
directive @context(name: String!) repeatable on OBJECT | INTERFACE | UNION
GRAPHQL;
    }
}

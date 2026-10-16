<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class FromContextDirective extends BaseDirective
{
    public const NAME = 'fromContext';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Selects a value from a named context for the router to pass as this argument.

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

https://www.apollographql.com/docs/graphos/schema-design/federated-schemas/reference/directives#fromcontext
"""
directive @fromContext(field: federation__ContextFieldValue) on ARGUMENT_DEFINITION
GRAPHQL;
    }
}

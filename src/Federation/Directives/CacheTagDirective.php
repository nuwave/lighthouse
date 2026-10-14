<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class CacheTagDirective extends BaseDirective
{
    public const NAME = 'cacheTag';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Defines a cache tag template for active cache invalidation in the router.

```graphql
type Query {
  users: [User!]! @cacheTag(format: "users-list")
  user(id: ID!): User @cacheTag(format: "user-{$args.id}")
}
type User @key(fields: "id") @cacheTag(format: "user-{$key.id}") {
  id: ID!
  name: String!
}
```

https://www.apollographql.com/docs/graphos/schema-design/federated-schemas/reference/directives#cachetag
"""
directive @cacheTag(format: String!) repeatable on FIELD_DEFINITION | OBJECT
GRAPHQL;
    }
}

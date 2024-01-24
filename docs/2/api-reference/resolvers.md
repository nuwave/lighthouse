# Resolvers

## Resolver function signature

Resolvers are always called with the same 4 arguments:

```php
<?php

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

public function resolve(
    $rootValue,
    array $args,
    GraphQLContext $context,
    ResolveInfo $resolveInfo
)
```

1. `$rootValue`: The result that was returned from the parent field.
   When resolving a field that sits on one of the root types (`Query`, `Mutation`) this is `null`.
2. `array $args`: The arguments that were passed into the field.
   For example, for a field call like `user(name: "Bob")` it would be `['name' => 'Bob']`
3. `GraphQLContext $context`: Arbitrary data that is shared between all fields of a single query.
   Lighthouse passes in an instance of `Nuwave\Lighthouse\Schema\Context` by default.
4. `ResolveInfo $resolveInfo`: Information about the query itself,
   such as the execution state, the field name, path to the field from the root, and more.

## Complexity function signature

The complexity function is used to calculate a query complexity score for a field.
You can define your own complexity function with the [@complexity](../api-reference/directives.md#complexity) directive.

```php
<?php

public function complexity(int $childrenComplexity, array $args): int
```

1. `$childrenComplexity`: The complexity of the children of the field. In case you expect to return
   multiple children, it can be useful to do some maths on this.
2. `array $args`: The arguments that were passed into the field.
   For example, for a field call like `user(name: "Bob")` it would be `['name' => 'Bob']`

Read more about query complexity in the [webonyx/graphql-php docs]([Read More](https://webonyx.github.io/graphql-php/security/#query-complexity-analysis))

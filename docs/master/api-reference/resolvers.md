# Resolvers

## Resolver function signature

Resolvers are always called with the same 4 arguments:

```php
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
```

1. `mixed $root`: The result that was returned from the parent field.
   When resolving a field that sits on one of the root types (`Query`, `Mutation`) this is `null`.
2. `array $args`: The arguments that were passed into the field.
   For example, for a field call like `user(name: "Bob")` it would be `['name' => 'Bob']`
3. `GraphQLContext $context`: Arbitrary data that is shared between all fields of a single query.
   Lighthouse passes in an instance of `Nuwave\Lighthouse\Schema\Context` by default.
4. `ResolveInfo $resolveInfo`: Information about the query itself,
   such as the execution state, the field name, path to the field from the root, and more.

The return value of this must fit the return type defined for the corresponding field from the schema.

## Root resolvers

Root resolvers are classes with an `__invoke` method that sit directly in the configured
`lighthouse.namespaces.queries` or `lighthouse.namespaces.mutations` namespaces.
Lighthouse calls them with positional arguments `($root, $args, $context, $resolveInfo)` where `$root` is always `null` for root types.

Omitting `$root` causes Lighthouse's positional `null` to bind to `$args`, producing TypeErrors at runtime.
The canonical signature for a root resolver is:

```php
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyQuery
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        // ...
    }
}
```

On PHP 8.2+, you can use the more precise `null` type instead of `mixed`.
The Rector rule below automatically picks the correct type for your PHP version.

### Rector rule

Lighthouse ships `RootResolverSignatureRector` to automatically fix root resolver signatures.
See [Automated Code Refactoring with Rector](../testing/rector.md) for setup and full documentation.

## Complexity function signature

The complexity function is used to calculate a query complexity score for a field.
You can define your own complexity function with the [@complexity](../api-reference/directives.md#complexity) directive.

```php
function (int $childrenComplexity, array $args): int
```

1. `$childrenComplexity`: The complexity of the children of the field. In case you expect to return
   multiple children, it can be useful to do some maths on this.
2. `array $args`: The arguments that were passed into the field.
   For example, for a field call like `user(name: "Bob")` it would be `['name' => 'Bob']`

Read more about query complexity in the [webonyx/graphql-php docs](https://webonyx.github.io/graphql-php/security/#query-complexity-analysis)

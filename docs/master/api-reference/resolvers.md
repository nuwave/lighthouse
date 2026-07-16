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

Lighthouse ships a Rector rule that enforces correct `__invoke` signatures on root resolvers.
Enable it in your `rector.php`:

```php
use Nuwave\Lighthouse\Rector\RootResolverSignatureRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(RootResolverSignatureRector::class);

    // Required: Larastan bootstrap makes config() available
    $rectorConfig->bootstrapFiles([
        __DIR__ . '/vendor/larastan/larastan/bootstrap.php',
    ]);
};
```

The rule fixes:

- Missing `$root` parameter (detected when the single param is typed `array`, assumed to be `$args`)
- Useless single root parameters (any single param that is not typed `array` is stripped entirely)
- Wrong type on the `$root` parameter (must be `null` on PHP 8.2+, `mixed` on earlier versions)
- Wrong type on `$args` (must be `array`)
- Wrong type on `$context` if present (must implement `GraphQLContext`)
- Wrong type on `$resolveInfo` if present (must be or extend `ResolveInfo`)

#### Name normalization

Optionally configure preferred parameter names:

```php
$rectorConfig->ruleWithConfiguration(RootResolverSignatureRector::class, [
    'paramNames' => ['_', 'args', 'context', 'resolveInfo'],
]);
```

Use `null` at any position to skip renaming that parameter:

```php
$rectorConfig->ruleWithConfiguration(RootResolverSignatureRector::class, [
    'paramNames' => [null, null, 'context', 'resolveInfo'],
]);
```

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

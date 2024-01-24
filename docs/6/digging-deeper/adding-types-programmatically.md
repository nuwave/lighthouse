# Adding Types Programmatically

You might want to add additional types to the schema programmatically.

## Additional Schema Definitions

If you want to use the SDL to define additional types dynamically,
you can listen for the [`BuildSchemaString`](../api-reference/events.md#buildschemastring)
event and return additional schema definitions as a string:

```php
$dispatcher = app(\Illuminate\Contracts\Events\Dispatcher::class);
$dispatcher->listen(
    \Nuwave\Lighthouse\Events\BuildSchemaString::class,
    function (): string {
        // You can get your schema from anywhere you want, e.g. a database, hardcoded
    }
);
```

When your schema is defined within files and you want to use `#import` to combine them,
you can use the `\Nuwave\Lighthouse\Schema\Source\SchemaStitcher` to load your file:

```php
$stitcher = new \Nuwave\Lighthouse\Schema\Source\SchemaStitcher(__DIR__ . '/path/to/schema.graphql');
return $stitcher->getSchemaString();
```

## Native PHP types

While Lighthouse is an SDL-first GraphQL server, you can also use native PHP type definitions.

Check out the [webonyx/graphql-php documentation](https://webonyx.github.io/graphql-php/type-definitions)
on how to define types.

Note that you will not have access to a large portion of Lighthouse functionality
that is provided through server-side directives and the definition is much more verbose.

Because of this, we do not recommend you use native PHP types for complex object types.

However, it can be advantageous to use native types for two use cases:

- [Enum types](https://webonyx.github.io/graphql-php/type-definitions/enums):
  Allows you to reuse existing constants in your code
- [Custom Scalar types](https://webonyx.github.io/graphql-php/type-definitions/scalars/#writing-custom-scalar-types).
  They will have to be implemented in PHP anyway

## Using the TypeRegistry

Lighthouse provides a type registry out of the box for you to register your types.
You can get an instance of it through the Laravel Container.

```php
use GraphQL\Type\Definition\Type;
use Illuminate\Support\ServiceProvider;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Schema\TypeRegistry;

final class GraphQLServiceProvider extends ServiceProvider
{
    public function boot(TypeRegistry $typeRegistry): void
    {
        $typeRegistry->register(
             new ObjectType([
                 'name' => 'User',
                 'fields' => function () use ($typeRegistry): array {
                     return [
                         'email' => [
                             'type' => Type::string(),
                         ],
                         'friends' => [
                             'type' => Type::listOf(
                                 $typeRegistry->get('User')
                             ),
                         ],
                     ];
                 }
             ])
        );
    }
}
```

If you register a lot of types, it can be beneficial for performance to register them lazily.
Make sure the name you use to register matches the name of the built type.

```php
$name = 'User';
$typeRegistry->registerLazy(
    $name,
    static function () use ($name, $typeRegistry): ObjectType {
        return new ObjectType([
            'name' => $name,
            'fields' => function () use ($typeRegistry): array {
                return [
                    'email' => [
                        'type' => Type::string(),
                    ],
                    'friends' => [
                        'type' => Type::listOf(
                            $typeRegistry->get('User')
                        ),
                    ],
                ];
            },
        ]);
    }
);
```

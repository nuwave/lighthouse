# Adding Types Programmatically

You might want to add additional types to the schema programmatically.

## Additional Schema Definitions

If you want to use the SDL to define additional types dynamically,
you can listen for the [`BuildSchemaString`](../api-reference/events.md#buildschemastring)
event and return additional schema definitions as a string:

```php
app('events')->listen(
    \Nuwave\Lighthouse\Events\BuildSchemaString::class,
    function(): string {
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

Check out the [webonyx/graphql-php documentation](http://webonyx.github.io/graphql-php/type-system/)
on how to define types.

Note that you will not have access to a large portion of Lighthouse functionality
that is provided through server-side directives and the definition is much more verbose.

Because of this, we do not recommend you use native PHP types for complex object types.

However, it can be advantageous to use native types for two use cases:

- [Enum types](http://webonyx.github.io/graphql-php/type-system/enum-types/):
  Allows you to reuse existing constants in your code
- [Custom Scalar types](http://webonyx.github.io/graphql-php/type-system/scalar-types/#writing-custom-scalar-types).
  They will have to be implemented in PHP anyway

## Using the TypeRegistry

Lighthouse provides a type registry out of the box for you to register your types.
You can get an instance of it through the Laravel Container.

```php
<?php

namespace App\Providers;

use GraphQL\Type\Definition\Type;
use Illuminate\Support\ServiceProvider;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class GraphQLServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param TypeRegistry $typeRegistry
     *
     * @return void
     */
    public function boot(TypeRegistry $typeRegistry): void
    {
        $typeRegistry->register(
             new ObjectType([
                 'name' => 'User',
                 'fields' => function() use ($typeRegistry): array {
                     return [
                         'email' => [
                             'type' => Type::string()
                         ],
                         'friends' => [
                             'type' => Type::listOf(
                                 $typeRegistry->get('User')
                             )
                         ]
                     ];
                 }
             ])
        );
    }
}
```

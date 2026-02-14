# Entities

A core component of a federation capable GraphQL service is the `_entities` field.
For a given `__typename` in the given `$representations`, Lighthouse will look for a resolver to return the full `_Entity`.

## Class Based Resolvers

Lighthouse will look for a class which name is equivalent to `__typename` in the namespace configured in `lighthouse.federation.entities_resolver_namespace`.

```graphql
{
  _entities(representations: [{ __typename: "Foo", id: 1 }]) {
    ... on Foo {
      id
    }
  }
}
```

### Single Entity Resolvers

After validating the type `Foo` exists, Lighthouse will look for a resolver class in `App\GraphQL\Entities\Foo`.
The resolver class is expected to contain a method `__invoke()` which takes a single argument: the array form of the representation.

```php
namespace App\GraphQL\Entities;

class Foo
{
    /**
     * @param  array{__typename: string, id: int}  $representation
     */
    public function __invoke(array $representation)
    {
        // TODO return a value that matches type Foo
    }
}
```

### Batched Entity Resolves

When the client requests a large number of entities with the same type, it can be more efficient to resolve them all at once.
When your entity resolver class implements `Nuwave\Lighthouse\Federation\BatchedEntityResolver`, Lighthouse will call it a single time with a list of all representations of its type.
The resolver can then do some kind of batch query to resolve them and return them all at once.

```php
namespace App\GraphQL\Entities;

use Nuwave\Lighthouse\Federation\BatchedEntityResolver;

class Foo implements BatchedEntityResolver
{
    /**
     * @param  array<int, array{__typename: string, id: int}>  $representations
     */
    public function __invoke(array $representations): iterable
    {
        // TODO return multiple values that match type Foo
    }
}
```

## Eloquent Model Resolvers

When no resolver class can be found, Lighthouse will attempt to find the model that matches the type `__typename`, using the namespaces configured in `lighthouse.namespaces.models`.

```graphql
{
  _entities(representations: [{ __typename: "Foo", bar: "asdf", baz: 42 }]) {
    ... on Foo {
      id
    }
  }
}
```

The additional fields in the representation constrain the query builder, which is then called and expected to return a single result.

```php
$results = App\Models\Foo::query()
    ->where('bar', 'asdf')
    ->where('baz', 42)
    ->get();

if ($results->count() > 1) {
    throw new Error('The query returned more than one result.');
}

return $results->first();
```

# Entities

A core component of a federation capable GraphQL service is the `_entities` field.
For a given `__typename` in the given `$representations`, Lighthouse will look for
a resolver to return the full `_Entity`.

## Class Based Resolvers

Lighthouse will look for a class which name is equivalent to `__typename` in the
namespace configured in `lighthouse.federation.entities_resolver_namespace`.

```graphql
{
  _entities(representations: [{ __typename: "Foo", id: 1 }]) {
    ... on Foo {
      id
    }
  }
}
```

After validating the type `Foo` exists, Lighthouse will look for a resolver class in `App\GraphQL\Entities\Foo`.
The resolver class is expected to contain a method `__invoke()` which takes a single argument:
the array form of the representation.

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

## Eloquent Model Resolvers

When no resolver class can be found, Lighthouse will attempt to find the model that
matches the type `__typename`, using the namespaces configured in `lighthouse.namespaces.models`.

```graphql
{
  _entities(representations: [{ __typename: "Foo", bar: "asdf", baz: 42 }]) {
    ... on Foo {
      id
    }
  }
}
```

The additional fields in the representation constrain the query builder, which is then
called and expected to return a single result.

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

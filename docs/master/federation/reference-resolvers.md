# Reference resolvers

To enable the current subgraph to provide entities for other subgraphs, you need to implement reference resolvers.
These reference resolvers act as helpers to enable cross-subgraph communication and provide data from one subgraph to another when needed.
Read more about reference resolvers in the [Apollo Federation docs](https://www.apollographql.com/docs/federation/v1/entities#reference-resolvers).

Lighthouse will look for a class which name is equivalent to `__typename` in the
namespace configured in `lighthouse.federation.entities_resolver_namespace`.

When you need to retrieve information from subgraphs,
the gateway automatically generates a request to the corresponding endpoint of the subgraph.
More details about this can be found in section [Query.\_entities of the Apollo Federation docs](https://www.apollographql.com/docs/federation/building-supergraphs/subgraphs-overview#query_entities).

An example of such a request is shown below:

```graphql
{
  _entities(representations: [{ __typename: "Foo", id: 1 }]) {
    ... on Foo {
      id
    }
  }
}
```

## Single Entity Resolvers

After validating that type `Foo` exists, Lighthouse will look for a resolver class
in the namespace configured in `lighthouse.federation.entities_resolver_namespace`.
The resolver class is expected to contain a method `__invoke()` which takes
a single argument: the array form of the representation.

```php
namespace App\GraphQL\ReferenceResolvers;

final class Foo
{
    /** @param array{__typename: string, id: int} $representation */
    public function __invoke(array $representation)
    {
        // TODO return a value that matches type Foo
    }
}
```

The method should return an object that has the same name as the entity, or additionally return the field `__typename`.

```php
namespace App\GraphQL\ReferenceResolvers;

use App\Repositories\FooRepository;
use Illuminate\Support\Arr;

final class Foo
{
    public function __invoke($representation): array
    {
        $id = Arr::get($representation, 'id');
        $foo = FooRepository::byID($id)->toArray();

        return Arr::add($foo, '__typename', 'Foo');
    }
}
```

## Batched Entity Resolvers

When the client requests a large number of entities with the same type, it can be more efficient to resolve them all at once.
When your entity resolver class implements `Nuwave\Lighthouse\Federation\BatchedEntityResolver`,
Lighthouse will call it a single time with an array of all representations of its type.
The resolver can then do some kind of batch query to resolve them and return them all at once.

```php
namespace App\GraphQL\ReferenceResolvers;

use Nuwave\Lighthouse\Federation\BatchedEntityResolver;

final class Foo implements BatchedEntityResolver
{
    /**
     * @param  array<string, array{__typename: string, id: int}>  $representations
     */
    public function __invoke(array $representations): iterable
    {
        // TODO return multiple values that match type Foo
    }
}
```

The returned iterable _must_ have the same keys as the given `array $representations`
to enable Lighthouse to return the results in the correct order.

```php
namespace App\GraphQL\ReferenceResolvers;

use App\Repositories\ProductRepository;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Federation\BatchedEntityResolver;

final class Product implements BatchedEntityResolver
{
    public function __invoke(array $representations): iterable
    {
        $ids = Arr::pluck($representations, 'id');

        $products = ProductRepository::byIDs($ids)
            ->keyBy('id');

        $result = [];
        foreach ($representations as $key => $representation) {
            $result[$key] = $products->get($representation['id']);
        }

        return $result;
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
In simplified terms, Lighthouse will do this:

```php
$results = App\Models\Foo::query()
    ->where('bar', 'asdf')
    ->where('baz', 42)
    ->get();

if ($results->count() > 1) {
    throw new GraphQL\Error\Error('The query returned more than one result.');
}

return $results->first();
```

The default model resolver makes one database query for each entity.
Therefore, for a large number of entities, it is worth considering [Batched Entity Resolvers](reference-resolvers.md#batched-entity-resolvers)
to avoid this issue.

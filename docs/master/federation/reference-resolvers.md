# Reference resolvers

To enable the current subgraph to provide entities for other subgraphs, you need to implement reference resolvers. These
reference resolvers act as helpers to enable cross-subgraph communication and provide data from one subgraph to another
when needed. Read more about the reference resolvers in
the [Apollo Federation docs](https://www.apollographql.com/docs/federation/v1/entities#reference-resolvers).

Lighthouse will look for a class which name is equivalent to `__typename` in the
namespace configured in `lighthouse.federation.entities_resolver_namespace`.

When you need to retrieve information from subgraphs, the gateway automatically generates a request to the corresponding
endpoint of the subgraph. More details about this can be found in
section [Query._entities of the Apollo Federation docs](https://www.apollographql.com/docs/federation/building-supergraphs/subgraphs-overview#query_entities)
.

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

After validating the type `Foo` exists, Lighthouse will look for a resolver class in
config `lighthouse.federation.entities_resolver_namespace` namespace. The resolver class is expected to contain a
method `__invoke()` which takes a single argument: the array form of the representation.

```php
namespace App\GraphQL\ReferenceResolvers;

final class Foo
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

The method should only return an object that has the same name as the entity, but the namespace of the object may be
different.

```php
namespace App\GraphQL\ReferenceResolvers;

use App\GraphQL\Entities\Foo as FooEntity;
use App\Repositories\SomeFooRepository;
use Illuminate\Support\Arr;

final class Foo
{
    public function __invoke($representation): FooEntity
    {
        $id = Arr::get($representation, 'id');
        $foo = SomeFooRepository::findBySomeQuery($id);

        return FooEntity::fromArray($foo);
    }
}
```

It is also possible to return an object of the current class, but in such case, you need to either create a new object
or return its copy. This is necessary because when returning more than one entity, all entities will be assigned the
value of the last entity since the resolver's object for all entities of the same type is the same.

```php
namespace App\GraphQL\ReferenceResolvers;

use App\Repositories\SomeFooRepository;
use Illuminate\Support\Arr;

final class Foo
{
    public string $id;
    public string $uuid;

    public function __invoke($representation): self
    {
        $id = Arr::get($representation, 'id');
        $foo = SomeFooRepository::findBySomeQuery($id);

        $this->id = Arr::get($foo, 'id');
        $this->uuid = Arr::get($foo, 'uuid');

        return clone $this; // or create new object
    }
}
```

## Batched Entity Resolves

When the client requests a large number of entities with the same type, it can be more efficient to resolve
them all at once. When your entity resolver class implements `Nuwave\Lighthouse\Federation\BatchedEntityResolver`,
Lighthouse will call it a single time with an array of all representations of its type. The resolver can then do
some kind of batch query to resolve them and return them all at once.

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

The returned iterable _must_ have the same keys as the given `array $representations` to enable Lighthouse
to return the results in the correct order.

```php
namespace App\GraphQL\ReferenceResolvers;

use App\Repositories\SomeProductRepository;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Federation\BatchedEntityResolver;

final class Product implements BatchedEntityResolver
{
    public function __invoke($representation): iterable
    {
        $result = [];
        $ids = Arr::pluck($representation, 'id');
        $products = SomeProductRepository::getSomeProductsCollection($ids);

        foreach ($representation as $hash => $representation) {
            $id = Arr::get($representation, 'id');

            $result[$hash] = $products->where('id', $id)->first(); // returned iterable _must_ have the same keys as the given `array $representations`
        }

        return $result;
    }
}
```

### Base Batched Entity Resolvers

For convenience, you can use a base class BaseBatchedReferenceResolver. He uses the `__invoke()` method to prepare the
input request and generate the response, as well as provides some auxiliary methods. So all you need to do is inherit
from the base class and implement the `resolve()` method, which should return a collection of results. The base class
will be responsible for generating the response.

```php
namespace App\GraphQL\ReferenceResolvers;

use App\Models\Product;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Federation\Resolvers\BaseBatchedReferenceResolver;

final class Product extends BaseBatchedReferenceResolver
{
    public function resolve(): Collection
    {
        $primaryKey = $this->getPrimaryKey();
        $ids = $this->getRepresentationIds()
    
        return Product::whereIn($primaryKey, $ids)->get();
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

For model resolvers, it is typical that they make one query to the database for each entity, which can lead to the "n+1"
problem. Therefore, for a large number of entities, it is worth
considering [Batched Entity Resolves](reference-resolvers.md#batched-entity-resolves) to avoid this issue.

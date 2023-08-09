# Entity representation

To reference an entity that originates in another subgraph, first subgraph needs to define a stub of that entity to make
its own schema valid. The stub includes just enough information for the subgraph to know how to uniquely identify a
particular entity in another subgraph.
Read more about the entity representation in the [Apollo Federation docs](https://www.apollographql.com/docs/federation/v1/entities/#entity-representations).

A representation always consists of:
- A `__typename` field;
- Values for the entity's primary key fields.

In this way, the resolver should return an array consisting of the `__typename` field, which corresponds to the entity's
name, and the primary key that can identify the entity.

## Example 1

In this example, subgraph for order service has an entity called `Order`, which in turn has an entity called `Receipt`
defined in a separate subgraph for payment service. The relationship between `Order` and `Receipt` is one-to-one.

```graphql
type Order {
    id: Int!
    receipt: Receipt! @field(resolver: "App\\GraphQL\\Resolvers\\Receipt")
}

type Receipt @key(fields: "uuid") @extends{
    uuid: String! @external
}
```

The resolver for receipt in order service returns an array consisting of:
- `__typename` - the entity name from the payment service;
- `uuid` - the primary key of the receipt.

```php
namespace App\GraphQL\Resolvers;

final class Receipt
{
    public function __invoke($model): array
    {
        return [
            '__typename' => 'Receipt',
            'uuid' => $model->receipt_id,
        ];
    }
}
```

## Example 2

In this example, subgraph for order service has an entity called `Order`, which in turn has an entity called `Product`
defined in a separate subgraph for product service. The relationship between `Order` and `Product` is one-to-many.

```graphql
type Order {
    sum: Int!
    products: [Product!]! @field(resolver: "App\\GraphQL\\Resolvers\\Products")
}

type Product @key(fields: "uuid") @extends{
    uuid: String! @external
}
```

The resolver for product in order service returns an array of arrays. Each sub-array consists of:
- `__typename` - the entity name from the product service;
- `uuid` - the primary key of a specific product.

```php
namespace App\GraphQL\Resolvers;

final class Products
{
    public function __invoke($model): array
    {
        return $model
            ->products()
            ->get()
            ->map(fn($product) => ['__typename' => 'Product', 'uuid' => $product->id])
            ->all();
    }
}
```

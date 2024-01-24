# Entity representation

To reference an entity that originates in another subgraph, first subgraph needs to define a stub of that entity to make its own schema valid.
The stub includes just enough information for the subgraph to know how to uniquely identify a particular entity in another subgraph.
Read more about the entity representation in the [Apollo Federation docs](https://www.apollographql.com/docs/federation/v1/entities/#entity-representations).

A representation always consists of:

- A `__typename` field;
- Values for the entity's primary key fields.

## Eloquent Model representation

If there is an Eloquent relationship between entities from different subgraphs, it's not mandatory to define an entity representation.
Lighthouse will automatically determine the necessary information based on the relationship directive.

```graphql
type Post {
  id: ID!
  title: String!
  comments: [Comment!]! @hasMany
}

type Comment @extends @key(fields: "id") {
  id: ID! @external
}
```

## Non-Eloquent representation

If entities don't have an Eloquent relationship within the subgraph, it's necessary to specify a separate resolver that will return the required information.
The resolver should return data containing information about the `__typename` field, which corresponds to the entity's name and the primary key that can identify the entity.
The `__typename` can either be provided as an explicit field or implicitly by returning an object with a matching class name.

### Example 1

In this example, subgraph for order service has an entity called `Order`, which in turn has an entity called `Receipt`
defined in a separate subgraph for payment service. The relationship between `Order` and `Receipt` is one-to-one.

```graphql
type Order {
  id: ID!
  receipt: Receipt!
}

type Receipt @extends @key(fields: "uuid") {
  uuid: ID! @external
}
```

The resolver for receipt in order service returns an array consisting of:

- `__typename` - the entity name from the payment service;
- `uuid` - the primary key of the receipt.

```php
namespace App\GraphQL\Types\Order;

final class Receipt
{
    public function __invoke($order): array
    {
        return [
            '__typename' => 'Receipt',
            'uuid' => $order->receipt_id,
        ];
    }
}
```

### Example 2

In this example, subgraph for order service has an entity called `Order`, which in turn has an entity called `Product`
defined in a separate subgraph for product service. The relationship between `Order` and `Product` is one-to-many.

```graphql
type Order {
  sum: Int!
  products: [Product!]!
}

type Product @extends @key(fields: "uuid") {
  uuid: ID! @external
}
```

The resolver for product in order service returns an array of arrays. Each sub-array consists of:

- `__typename` - the entity name from the product service;
- `uuid` - the primary key of a specific product.

```php
namespace App\GraphQL\Types\Order;

final class Products
{
    public function __invoke($order): iterable
    {
        return ProductService::retrieveProductsForOrder($order)
            ->map(fn (Product $product): array => [
                '__typename' => 'Product',
                'uuid' => $product->id,
            ]);
    }
}
```

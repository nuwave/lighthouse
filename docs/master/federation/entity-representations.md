# Entity representation

In order for a subgraph to be able to return an entity from another subgraph, it is necessary to define in the first
subgraph the representation of the required entity from those fields that are known to the first subgraph.
Read more about the entity representation in the [Apollo Federation docs](https://www.apollographql.com/docs/federation/v1/entities/#entity-representations).

A representation always consists of:
- A `__typename` field;
- Values for the entity's primary key fields.

In this way, the resolver should return an array consisting of the `__typename` field, which corresponds to the entity's
name, and the primary key that can identify the entity.

### Example 1

In this example, the `review-service` has an entity called `Review`, which in turn has an entity called `Product`
defined in a separate `product-service`. The relationship between the review and the product is one-to-one.

```graphql
type Review {
    score: Int!
    product: Product! @field(resolver: "App\\GraphQL\\Resolvers\\Product")
}

type Product @key(fields: "uuid") @extends{
    uuid: String! @external
}
```

The resolver for the product in the `review-service` returns an array consisting of:
- `__typename` - the entity name from the `product-service`;
- `uuid` - the primary key of the product.

```php
namespace App\GraphQL\Resolvers;

final class Product
{
    public function __invoke($model): array
    {
        return [
            '__typename' => 'Product',
            'uuid'       => $model->product_id,
        ];
    }
}
```

### Example 2

In this example, the `order-service` has an entity called `Order`, which in turn has an entity called `Product`
defined in a separate `product-service`. The relationship between the review and the product is one-to-many.

```graphql
type Order {
    sum: Int!
    products: [Product!]! @field(resolver: "App\\GraphQL\\Resolvers\\Products")
}

type Product @key(fields: "uuid") @extends{
    uuid: String! @external
}
```

The resolver for the product in the `order-service` returns an array of arrays. Each sub-array consists of:
- `__typename` - the entity name from the `product-service`;
- `uuid` - the primary key of a specific product.

```php
namespace App\GraphQL\Resolvers;

final class Products
{
    public function __invoke($model): array
    {
        $products = [];

        $model->products()->each(function ($product) use (&$products){
            $products[] = ['__typename' => 'Product', 'uuid' => $product->id];
        });

        return $products;
    }
}
```

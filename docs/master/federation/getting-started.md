# Federation: Getting Started

**Experimental: not enabled by default, not guaranteed to be stable.**

Federation enables you to combine GraphQL services into a single unified data graph.
Read more about the core concepts and motivation in the [Apollo Federation docs](https://www.apollographql.com/docs/federation).

Lighthouse can act as a federation capable service as described in the [Apollo Federation specification](https://www.apollographql.com/docs/federation/federation-spec) v2.
It can not serve as a [federation gateway](https://www.apollographql.com/docs/federation/gateway).

## Setup

Register the service provider `Nuwave\Lighthouse\Federation\FederationServiceProvider`, see [registering providers in Laravel](https://laravel.com/docs/providers#registering-providers).

## Publishing Your Schema

In order to generate a `.graphql` schema file suitable for publishing, use the `--federation` option of [`print-schema`](../api-reference/commands.md#print-schema).

```shell
php artisan lighthouse:print-schema --federation
```

## Apollo Federation v2

Support for Apollo Federation v2 is `opt-in` and can be enabled by adding the following to your schema.
See [the Apollo documentation on federated directives](https://www.apollographql.com/docs/graphos/schema-design/federated-schemas/reference/directives) for the latest spec.

```graphql
extend schema
  @link(
    url: "https://specs.apollo.dev/federation/v2.12"
    import: [
      "@authenticated"
      "@cacheTag"
      "@composeDirective"
      "@context"
      "@cost"
      "@extends"
      "@external"
      "@fromContext"
      "@inaccessible"
      "@interfaceObject"
      "@key"
      "@listSize"
      "@override"
      "@policy"
      "@provides"
      "@requires"
      "@requiresScopes"
      "@shareable"
      "@tag"
    ]
  )
```

The imported directives are preserved in `_service.sdl` and in `lighthouse:print-schema --federation` output, including annotations on arguments, scalars, enums, interfaces, unions, and input fields.
The router handles authentication (`@authenticated`), JWT scopes (`@requiresScopes`), authorization policies (`@policy`), contextual argument values (`@context` and `@fromContext`), demand control (`@cost` and `@listSize`), and cache invalidation (`@cacheTag`).
These directives declare router behavior and do not add local Laravel authorization, cost limits, or caching.

Use Federation v2.5 or later for authentication and scopes, v2.6 for policies, v2.8 for contexts, v2.9 for demand control, and v2.12 for cache tags.
The optional `label` argument on `@override` supports progressive field migration starting with Federation v2.7.
For example:

```graphql
type Product
  @key(fields: "id")
  @authenticated
  @cacheTag(format: "product-{$key.id}") {
  id: ID!
  name: String @requiresScopes(scopes: [["read:products"], ["admin"]])
  price: Int @override(from: "catalog", label: "percent(10)")
}
```

## Federated tracing

In order to use federated tracing, you need to enable [tracing](../performance/tracing.md) and set the driver to `Nuwave\Lighthouse\Tracing\FederatedTracing\FederatedTracing::class` in your `config/lighthouse.php`:

```php
'tracing' => [
    'driver' => Nuwave\Lighthouse\Tracing\FederatedTracing\FederatedTracing::class,
],
```

Note that federated tracing requires `google/protobuf` to be installed (for better performance you can also install the `protobuf` php extension).

### Unsupported features

Some features of the Apollo Federation specification **are not supported** by Lighthouse:

#### Renaming directives

Renaming imported directives is not supported.
You can only use the default names.

```graphql
extend schema
  @link(
    url: "https://specs.apollo.dev/federation/v2.3"
    import: [{ name: "@key", as: "@uniqueKey" }, "@shareable"]
  )
```

#### Namespaced directives

Using directives from a namespace without an import is not supported.
You should import the directive and use the default name.

```graphql
extend schema
  @link(url: "https://specs.apollo.dev/federation/v2.3", import: ["@key"])

type Book @federation__shareable {
  title: String!
}
```

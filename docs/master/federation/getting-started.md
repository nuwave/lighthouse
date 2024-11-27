# Federation: Getting Started

**Experimental: not enabled by default, not guaranteed to be stable.**

Federation enables you to combine GraphQL services into a single unified data graph.
Read more about the core concepts and motivation in the [Apollo Federation docs](https://www.apollographql.com/docs/federation).

Lighthouse can act as a federation capable service as described in the [Apollo Federation specification](https://www.apollographql.com/docs/federation/federation-spec) v2.
It can not serve as a [federation gateway](https://www.apollographql.com/docs/federation/gateway).

## Setup

Register the service provider `Nuwave\Lighthouse\Federation\FederationServiceProvider`,
see [registering providers in Laravel](https://laravel.com/docs/providers#registering-providers).

## Publishing Your Schema

In order to generate a `.graphql` schema file suitable for publishing, use the `--federation` option of [`print-schema`](../api-reference/commands.md#print-schema).

```sh
php artisan lighthouse:print-schema --federation
```

## Apollo Federation v2

Support for Apollo Federation v2 is `opt-in` and can be enabled by adding the following to your schema.
See [the Apollo documentation on federated directives](https://www.apollographql.com/docs/federation/federated-types/federated-directives) for the latest spec.

```graphql
extend schema
  @link(
    url: "https://specs.apollo.dev/federation/v2.3"
    import: [
      "@composeDirective"
      "@extends"
      "@external"
      "@inaccessible"
      "@interfaceObject"
      "@key"
      "@override"
      "@provides"
      "@requires"
      "@shareable"
      "@tag"
    ]
  )
```

## Federated tracing

In order to use federated tracing, you need to enabled [tracing](../performance/tracing.md)
and set the driver to `Nuwave\Lighthouse\Tracing\FederatedTracing\FederatedTracing::class` in your `config/lighthouse.php`:

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

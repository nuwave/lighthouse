# Federation: Getting Started

Federation enables you to combine GraphQL services into a single unified data graph.
Read more about the core concepts and motivation in the [Apollo Federation docs](https://www.apollographql.com/docs/federation).

Lighthouse can act as a federation capable service as described in the [Apollo Federation specification](https://www.apollographql.com/docs/federation/federation-spec).
It can not serve as a [federation gateway](https://www.apollographql.com/docs/federation/gateway/).

## Setup

Add the service provider to your `config/app.php`:

```php
'providers' => [
    \Nuwave\Lighthouse\Federation\FederationServiceProvider::class,
],
```

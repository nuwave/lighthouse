# Tracing

Tracing offers field-level performance monitoring for your GraphQL server.

## Setup

Register the service provider `Nuwave\Lighthouse\Tracing\TracingServiceProvider`,
see [registering providers in Laravel](https://laravel.com/docs/providers#registering-providers).

## Drivers

Lighthouse tracing is implemented though drivers, this allows supporting different tracing formats.

Lighthouse includes the following drivers:

- `Nuwave\Lighthouse\Tracing\ApolloTracing\ApolloTracing::class` (default) which implements [Apollo Tracing response format](https://github.com/apollographql/apollo-tracing#response-format)
- `Nuwave\Lighthouse\Tracing\FederatedTracing\FederatedTracing::class` which implements [Apollo Federated tracing](https://www.apollographql.com/docs/federation/metrics/)

### Federated tracing

Federated tracing driver requires `google/protobuf` to be installed.
For better performance you can also install the `protobuf` php extension.

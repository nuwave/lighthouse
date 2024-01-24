# Tracing

Tracing offers field-level performance monitoring for your GraphQL server.
Lighthouse follows the [Apollo Tracing response format](https://github.com/apollographql/apollo-tracing#response-format).

## Setup

Add the service provider to your `config/app.php`

```php
'providers' => [
    \Nuwave\Lighthouse\Tracing\TracingServiceProvider::class,
],
```

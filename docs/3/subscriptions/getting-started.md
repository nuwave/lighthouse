# Subscriptions: Getting Started

Subscriptions allow GraphQL clients to observe specific events
and receive updates from the server when those events occur.

::: tip NOTE
Much of the credit should be given to the [Ruby implementation](https://github.com/rmosolgo/graphql-ruby/blob/master/guides/subscriptions/overview.md) as they provided a great overview of how the backend should work.
:::

## Setup

Install the [Pusher PHP Library](https://github.com/pusher/pusher-http-php) for interacting with the Pusher HTTP API.

    composer require pusher/pusher-php-server

Add the service provider to your `config/app.php`

```php
'providers' => [
    \Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider::class,
],
```

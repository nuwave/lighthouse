# Subscriptions: Getting Started

Subscriptions allow GraphQL clients to observe specific events
and receive updates from the server when those events occur.

::: tip NOTE
Much of the credit should be given to the [Ruby implementation](https://github.com/rmosolgo/graphql-ruby/blob/master/guides/subscriptions/overview.md) as they provided a great overview of how the backend should work.
:::

## Setup

Add the service provider to your `config/app.php`

```php
'providers' => [
    \Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider::class,
],
```

If you want to use the Pusher driver, you need to install the [Pusher PHP Library](https://github.com/pusher/pusher-http-php) for interacting with the Pusher HTTP API.

    composer require pusher/pusher-php-server

If you want to use the Laravel Echo driver, you need to set the env `LIGHTHOUSE_BROADCASTER=echo`.

## Expiring Subscriptions

Subscriptions do not expire by themselves.
Unless you delete a subscription, it will continue to broadcast events after the client has disconnected.

The easiest way to expire subscriptions automatically is to use the env `LIGHTHOUSE_CACHE_TTL`
to set an expiration time in seconds (e.g. `LIGHTHOUSE_CACHE_TTL=3600` to expire in one hour).

### Pusher Expiration Webhook

If you are using the Pusher driver, you can use a `Presence` webhook to mitigate this problem.
When a Pusher channel is abandoned (ie. unsubscribed), it will trigger the webhook,
which will instruct Lighthouse to delete the subscription.

The webhook URL will typically be:

```
/graphql/subscriptions/webhook
```

You can add the webhook in the Pusher Dashboard. Select the type `Presence`.

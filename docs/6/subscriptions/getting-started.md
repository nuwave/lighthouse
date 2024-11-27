# Subscriptions: Getting Started

Subscriptions allow GraphQL clients to observe specific events
and receive updates from the server when those events occur.

::: tip NOTE
Much of the credit should be given to the [Ruby implementation](https://github.com/rmosolgo/graphql-ruby/blob/master/guides/subscriptions/overview.md) as they provided a great overview of how the backend should work.
:::

## Setup

Register the service provider `Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider`,
see [registering providers in Laravel](https://laravel.com/docs/providers#registering-providers).

If you want to use [the Pusher driver](https://laravel.com/docs/11.x/broadcasting#pusher-channels), you need to install the [Pusher PHP Library](https://github.com/pusher/pusher-http-php)
for interacting with the Pusher HTTP API.

    composer require pusher/pusher-php-server

If you want to use [the Laravel Echo driver](https://laravel.com/docs/broadcasting#client-side-installation),
you need to set the env `LIGHTHOUSE_BROADCASTER=echo`.

When using subscriptions with [Laravel Octane](https://laravel.com/docs/octane),
add the following to your `config/octane.php`:

```php
    'warm' => [
        ...Octane::defaultServicesToWarm(),
        Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry::class,
    ],
```

### Empty Response Optimization

Lighthouse returns the subscription channel as part of the response under `extensions`.
If `subscriptions.exclude_empty` in `lighthouse.php` is set to `true`,
API responses without a subscription channel will not contain `lighthouse_subscriptions` in `extensions`.
This optimizes performance by sending less data, but clients must anticipate this appropriately.

## Expiring Subscriptions

Subscriptions do not expire by themselves.
Unless you delete a subscription, it will continue to broadcast events after the client has disconnected.

The easiest way to expire subscriptions automatically is to use the env `LIGHTHOUSE_SUBSCRIPTION_STORAGE_TTL`
to set an expiration time in seconds (e.g. `LIGHTHOUSE_SUBSCRIPTION_STORAGE_TTL=3600` to expire in one hour).

### Pusher Expiration Webhook

If you are using the Pusher driver, you can use a [`channel existence`](https://pusher.com/docs/channels/server_api/webhooks/#channel-existence-events) webhook to mitigate this problem.
When a Pusher channel is vacated (i.e. there are no subscribers), it will trigger the webhook,
which will instruct Lighthouse to delete the subscription.

The webhook URL will typically be:

```
/graphql/subscriptions/webhook
```

You can add the webhook in the Pusher Dashboard. Select the type `channel existence`.

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

### Pusher Webhook

Subscriptions do not expire by themselves.
Unless a subscription is deleted, it will continue to broadcast events after the client has disconnected.

Using a `Presence` webhook will mitigate this problem.
When a Pusher channel is abandoned (ie. unsubscribed), it will trigger the webhook,
which will instruct Lighthouse to delete the subscription.

The webhook URL will typically be:

```
/graphql/subscriptions/webhook
```

You can add the webhook in the Pusher Dashboard. Select the type `Presence`.

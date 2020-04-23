# Trigger Subscriptions

Now that clients can subscribe to a field, you will need to notify Lighthouse
when the underlying data has changed.

## Broadcast Directive

The [`@broadcast`](../api-reference/directives.md#broadcast)
directive will broadcast all updates to the `Post` model to the `postUpdated` subscription.

```graphql
type Mutation {
  updatePost(input: UpdatePostInput!): Post
    @broadcast(subscription: "postUpdated")
}
```

You can reference the same subscription from multiple fields, or vice-versa
trigger multiple subscriptions from a single field.

## Fire Subscriptions From Code

The `Subscription` class offers a utility method `broadcast`
that can be used to broadcast subscriptions from anywhere in your application.

It accepts three parameters:

- `string $subscriptionField` The name of the subscription field you want to trigger
- `mixed $root` The result object you want to pass through
- `bool $shouldQueue = null` Optional, overrides the default configuration `lighthouse.subscriptions.queue_broadcasts`

The following example shows how to trigger a subscription after an update
to the `Post` model.

```php
$post->title = $newTitle;
$post->save();

\Nuwave\Lighthouse\Execution\Utils\Subscription::broadcast('postUpdated', $post);
```

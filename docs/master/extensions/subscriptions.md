# GraphQL Subscriptions

## Requirements

**Install the Pusher PHP Server package**

::: tip NOTE
A future version of Lighthouse will introduce a driver implementation so different websocket solutions can be leveraged by adjusting the config file and installing the required external package(s).
:::

```bash
composer require pusher/pusher-php-server
```

**Enable the extension in the lighthouse.php config file**

```php
'extensions' => [
    // ...
    \Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension::class,
],
```

## Basic Setup

TODO: Add description here...

```graphql
type Mutation {
    updatePost(input: UpdatePostInput!): Post
        # This will pipe the Post returned from this mutation to the
        # PostUpdatedSubscription resolve function
        @broadcast(subscription: "postUpdated")
}

type Subscription {
    postUpdated(author: ID): Post
        @subscription(
            class: "App\\GraphQL\\Subscriptions\\PostUpdatedSubscription"
        )
}
```

## The Subscription Class

TODO: Go through example showing all class methods...
TODO: Create example that creates a custom topic name to help filter listeners...

```php
namespace App\GraphQL\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class PostUpdatedSubscription extends GraphQLSubscription
{
    /**
     * Check if subscriber can listen to the subscription.
     *
     * @param Subscriber $subscriber
     * @param Request    $request
     *
     * @return bool
     */
    public function authorize(Subscriber $subscriber, Request $request)
    {
        $user = $subscriber->context->user;

        return $user->hasPermission('some-permission');
    }

    /**
     * Filter subscribers who should receive subscription.
     *
     * @param Subscriber $subscriber
     * @param mixed      \App\Models\Event $root
     *
     * @return bool
     */
    public function filter(Subscriber $subscriber, $root)
    {
        $user = $subscriber->context->user;

        // Don't send the subscription update to the same
        // person who updated the post.
        return $root->updated_by !== $user->id;
    }
}
```

## Firing a subscription via code

**Using an event listener**

```php
namespace App\Listeners\Post;

use App\Http\GraphQL\Subscriptions\PostUpdatedSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

class BroadcastPostUpdated
{
    /**
     * @var BroadcastsSubscriptions
     */
    protected $broadcaster;

    /**
     * @var BroadcastsSubscriptions
     */
    protected $subscription;

    /**
     * Create the event listener.
     *
     * @param BroadcastsSubscriptions  $broadcaster
     * @param PostUpdatedSubscription $subscription
     */
    public function __construct(
        BroadcastsSubscriptions $broadcaster,
        PostUpdatedSubscription $subscription
    ) {
        $this->broadcaster = $broadcaster;
        $this->subscription = $subscription;
    }

    /**
     * Handle the event.
     *
     * @param PostUpdatedEvent $event
     */
    public function handle(PostUpdatedEvent $event)
    {
        $this->broadcaster->broadcast(
            $this->subscription, // <-- The subscription class you created
            'postUpdated', // <-- Name of the subscription field to broadcast
            $event->post // <-- The root object that will be passed into the subscription resolver
        );
    }
}
```

## Apollo Link

To use Lighthouse's subscriptions for Apollo's client side library you'll need to create an `apollo-link`

```js
import { ApolloLink, Observable } from "apollo-link";

class PusherLink extends ApolloLink {
    constructor(options) {
        super();
        // Retain a handle to the Pusher client
        this.pusher = options.pusher;
    }

    request(operation, forward) {
        return new Observable(observer => {
            // Check the result of the operation
            forward(operation).subscribe({
                next: data => {
                    // If the operation has the subscription extension, it's a subscription
                    const subscriptionChannel = this._getChannel(
                        data,
                        operation
                    );

                    if (subscriptionChannel) {
                        this._createSubscription(subscriptionChannel, observer);
                    } else {
                        // No subscription found in the response, pipe data through
                        observer.next(data);
                        observer.complete();
                    }
                }
            });
        });
    }

    _getChannel(data, operation) {
        return !!data.extensions &&
            !!data.extensions.lighthouse_subscriptions &&
            !!data.extensions.lighthouse_subscriptions.channels
            ? data.extensions.lighthouse_subscriptions.channels[
                  operation.operationName
              ]
            : null;
    }

    _createSubscription(subscriptionChannel, observer) {
        const pusherChannel = this.pusher.subscribe(subscriptionChannel);
        // Subscribe for more update
        pusherChannel.bind("lighthouse-subscription", payload => {
            if (!payload.more) {
                // This is the end, the server says to unsubscribe
                this.pusher.unsubscribe(subscriptionChannel);
                observer.complete();
            }
            const result = payload.result;
            if (result) {
                // Send the new response to listeners
                observer.next(result);
            }
        });
    }
}

export default PusherLink;
```

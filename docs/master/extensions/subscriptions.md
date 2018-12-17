# GraphQL Subscriptions

::: tip NOTE
Much of the credit should be given to the [Ruby implementation](https://github.com/rmosolgo/graphql-ruby/blob/master/guides/subscriptions/overview.md) as they provided a great overview of how the backend should work.
:::

## Requirements

**Install the Pusher PHP Server package**

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

To fire a subscription from the server down to the client you must create a `Subscription` type with fields decorated with the `@subscription` directive which will point to the `GraphQLSubscription` class responsible for managing the subscribed client(s).

```graphql
type Subscription {
    postUpdated(author: ID): Post
        @subscription(
            class: "App\\GraphQL\\Subscriptions\\PostUpdatedSubscription"
        )
}

type Mutation {
    updatePost(input: UpdatePostInput!): Post
        # This will pipe the Post returned from this mutation to the
        # PostUpdatedSubscription resolve function
        @broadcast(subscription: "postUpdated")
}
```

## The Subscription Class

All subscriptions must have a defined `GraphQLSubscription` class which defines methods for authorization, filtering, etc. At a minimum, the assigned `GraphQLSubscription` must define the `authorize` and `filter` methods.

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
        $author = \App\Models\Author::find($subscriber->args['author']);

        return $user->can('viewPosts', $author);
    }

    /**
     * Filter subscribers who should receive subscription.
     *
     * @param Subscriber $subscriber
     * @param mixed      $root
     *
     * @return bool
     */
    public function filter(Subscriber $subscriber, $root)
    {
        $user = $subscriber->context->user;

        // Don't broadcast the subscription to the same
        // person who updated the post.
        return $root->updated_by !== $user->id;
    }

    /**
     * Encode topic name.
     *
     * @param Subscriber $subscriber
     *
     * @return string
     */
    public function encodeTopic(Subscriber $subscriber, $fieldName)
    {
        // Optionally create a unique topic name based on the
        // `author` argument.
        $args = $subscriber->args;

        return snake_case($fieldName).':'.$args['author'];
    }

    /**
     * Decode topic name.
     *
     * @param string           $operationName
     * @param \App\Models\Post $root
     * @param mixed            $context
     *
     * @return string
     */
    public function decodeTopic(string $fieldName, $root)
    {
        // Decode the topic name if the `encodeTopic` has been overwritten.
        $author_id = $root->author_id;

        return snake_case($fieldName).':'.$author_id;
    }

    /**
     * Resolve the subscription.
     *
     * @param \App\Models\Post $root
     * @param array            $args
     * @param Context          $context
     * @param ResolveInfo      $info
     *
     * @return mixed
     */
    public function resolve($root, array $args, $context, ResolveInfo $info)
    {
        // Optionally manipulate the `$root` item before it gets broadcasted to
        // subscribed client(s).
        $root->load(['author', 'author.achievements']);

        return $root;
    }
}
```

## Firing a subscription via code

The `Subscription` utility class can be used to broadcast subscriptions via code. It accepts the subscription field, the `$root` object you want to pass through and an optional boolean to override the `lighthouse.subscriptions.queue_broadcasts` configuration setting.

```php
\Nuwave\Lighthouse\Execution\Utils\Subscription::broadcast('postUpdated', $post);
```

## Filtering Subscriptions

There are times when you'll need to filter out specific events based on the arguments provided by the client. To handle this, you can return a true/false from the `filter` function to indicate whether the client should receive the subscription. For instance, using the following example:

```graphql
subscription onPostUpdated($post_id: ID!) {
    postUpdated(post_id: $post_id) {
        id
        title
        content
    }
}
```

To ensure only clients who are subscribed to a certain `post_id` receive an update, we can create a `filter`:

```php
namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Schema\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class PostUpdatedSubscription extends GraphQLSubscription
{
    /**
     * Filter subscribers who should receive subscription.
     *
     * @param Subscriber       $subscriber
     * @param \App\Models\Post $root
     *
     * @return bool
     */
    public function filter(Subscriber $subscriber, $root)
    {
        // Clients arguments when subscribing
        $args = $subscriber->args;

        // Ensure that the Post ($root) id matches
        // the requested `post_id`
        return $root->id == $args['post_id'];
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

Then initialize the pusher client and use it in the link stack.

```js
const pusherLink = new PusherLink({
    pusher: new Pusher(PUSHER_API_KEY, {
        cluster: PUSHER_CLUSTER,
        authEndpoint: `${API_LOCATION}/graphql/subscriptions/auth`,
        auth: {
            headers: {
                authorization: BEARER_TOKEN
            }
        }
    })
});

const link = ApolloLink.from([pusherLink, httpLink(`${API_LOCATION}/graphql`)]);
```

## Relay Modern Handler

To use Lighthouse's subscriptions with Relay Modern you'll need to create a custom handler and inject it into Relay's environment.

```js
import Pusher from "pusher-js";
import { Environment, Network, RecordSource, Store } from "relay-runtime";

const pusherClient = new Pusher(PUSHER_API_KEY, {
    cluster: "us2",
    authEndpoint: `${API_LOCATION}/graphql/subscriptions/auth`,
    auth: {
        headers: {
            authorization: BEARER_TOKEN
        }
    }
});

const createHandler = options => {
    let channelName;
    const { pusher, fetchOperation } = options;

    return (operation, variables, cacheConfig, observer) => {
        fetchOperation(operation, variables, cacheConfig)
            .then(response => {
                return response.json();
            })
            .then(response => {
                channelName =
                    !!response.extensions &&
                    !!response.extensions.lighthouse_subscriptions &&
                    !!response.extensions.lighthouse_subscriptions.channels
                        ? response.extensions.lighthouse_subscriptions.channels[
                              operation.name
                          ]
                        : null;

                if (!channelName) {
                    return;
                }

                const channel = pusher.subscribe(channelName);

                channel.bind("lighthouse-subscription", payload => {
                    const result = payload.result;
                    if (result && result.errors) {
                        observer.onError(result.errors);
                    } else if (result) {
                        observer.onNext({
                            data: result.data
                        });
                    }
                    if (!payload.more) {
                        observer.onCompleted();
                    }
                });
            });

        return {
            dispose: () => pusher.unsubscribe(channelName)
        };
    };
};

const fetchOperation = (operation, variables, cacheConfig) => {
    const bodyValues = {
        variables,
        query: operation.text,
        operationName: operation.name
    };

    return fetch(`${API_LOCATION}/graphql`, {
        method: "POST",
        opts: {
            credentials: "include"
        },
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            Authorization: BEARER_TOKEN
        },
        body: JSON.stringify(bodyValues)
    });
};

const fetchQuery = (operation, variables, cacheConfig) => {
    return fetchOperation(operation, variables, cacheConfig).then(response => {
        return response.json();
    });
};

const subscriptionHandler = createHandler({
    pusher: pusherClient,
    fetchOperation: fetchOperation
});

const network = Network.create(fetchQuery, subscriptionHandler);

export const environment = new Environment({
    network,
    store: new Store(new RecordSource())
});
```

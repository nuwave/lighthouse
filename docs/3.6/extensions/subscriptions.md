# GraphQL Subscriptions

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

## Defining Fields

Define your subscriptions as field on the root `Subscription` type in your schema.

```graphql
type Subscription {
  postUpdated(author: ID): Post
}
```

The quickest way to define such a field is through the `artisan` generator command:

    php artisan lighthouse:subscription PostUpdated

Lighthouse will look for a class with the capitalized name of the field that
is defined within the default subscription namespace.
For example, the field `postUpdated` should have a corresponding class at
`App\GraphQL\Subscriptions\PostUpdated`.

All subscription field classes **must** implement the abstract class
`Nuwave\Lighthouse\Schema\Types\GraphQLSubscription` and implement two methods:
`authorize` and `filter`.

```php
<?php

namespace App\GraphQL\Subscriptions;

use App\User;
use App\Post;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class PostUpdated extends GraphQLSubscription
{
    /**
     * Check if subscriber is allowed to listen to the subscription.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        $user = $subscriber->context->user;
        $author = User::find($subscriber->args['author']);

        return $user->can('viewPosts', $author);
    }

    /**
     * Filter which subscribers should receive the subscription.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @param  mixed  $root
     * @return bool
     */
    public function filter(Subscriber $subscriber, $root): bool
    {
        $user = $subscriber->context->user;

        // Don't broadcast the subscription to the same
        // person who updated the post.
        return $root->updated_by !== $user->id;
    }

    /**
     * Encode topic name.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @param  string  $fieldName
     * @return string
     */
    public function encodeTopic(Subscriber $subscriber, string $fieldName): string
    {
        // Optionally create a unique topic name based on the
        // `author` argument.
        $args = $subscriber->args;

        return Str::snake($fieldName).':'.$args['author'];
    }

    /**
     * Decode topic name.
     *
     * @param  string  $fieldName
     * @param  \App\Post  $root
     * @return string
     */
    public function decodeTopic(string $fieldName, $root): string
    {
        // Decode the topic name if the `encodeTopic` has been overwritten.
        $author_id = $root->author_id;

        return Str::snake($fieldName).':'.$author_id;
    }

    /**
     * Resolve the subscription.
     *
     * @param  \App\Post  $root
     * @param  array<string, mixed>  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return mixed
     */
    public function resolve($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Post
    {
        // Optionally manipulate the `$root` item before it gets broadcasted to
        // subscribed client(s).
        $root->load(['author', 'author.achievements']);

        return $root;
    }
}
```

If the default namespaces are not working with your application structure
or you want to be more explicit, you can use the [`@subscription`](../api-reference/directives.md#subscription)
directive to point to a different class.

## Trigger Subscriptions

Now that clients can subscribe to a field, you will need to notify Lighthouse
when the underlying data has changed.

### Broadcast Directive

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

### Fire Subscriptions From Code

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
     * Filter which subscribers should receive the subscription.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @param  mixed  $root
     * @return bool
     */
    public function filter(Subscriber $subscriber, $root): bool
    {
        // Clients arguments when subscribing
        $args = $subscriber->args;

        // Ensure that the Post ($root) id matches
        // the requested `post_id`
        return $root->id == $args['post_id'];
    }
}
```

## Client Implementations

To get you up and running quickly, the following sections show how to use subcriptions
with common GraphQL client libraries.

### Apollo

To use Lighthouse subscriptions with the [Apollo](https://www.apollographql.com/docs/react/)
client library you will need to create an `apollo-link`

```js
import { ApolloLink, Observable } from "apollo-link";

class PusherLink extends ApolloLink {
  constructor(options) {
    super();
    // Retain a handle to the Pusher client
    this.pusher = options.pusher;
  }

  request(operation, forward) {
    return new Observable((observer) => {
      // Check the result of the operation
      forward(operation).subscribe({
        next: (data) => {
          // If the operation has the subscription extension, it's a subscription
          const subscriptionChannel = this._getChannel(data, operation);

          if (subscriptionChannel) {
            this._createSubscription(subscriptionChannel, observer);
          } else {
            // No subscription found in the response, pipe data through
            observer.next(data);
            observer.complete();
          }
        },
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
    pusherChannel.bind("lighthouse-subscription", (payload) => {
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
        authorization: BEARER_TOKEN,
      },
    },
  }),
});

const link = ApolloLink.from([pusherLink, httpLink(`${API_LOCATION}/graphql`)]);
```

### Relay Modern

To use Lighthouse's subscriptions with Relay Modern you will
need to create a custom handler and inject it into Relay's environment.

```js
import Pusher from "pusher-js";
import { Environment, Network, RecordSource, Store } from "relay-runtime";

const pusherClient = new Pusher(PUSHER_API_KEY, {
  cluster: "us2",
  authEndpoint: `${API_LOCATION}/graphql/subscriptions/auth`,
  auth: {
    headers: {
      authorization: BEARER_TOKEN,
    },
  },
});

const createHandler = (options) => {
  let channelName;
  const { pusher, fetchOperation } = options;

  return (operation, variables, cacheConfig, observer) => {
    fetchOperation(operation, variables, cacheConfig)
      .then((response) => {
        return response.json();
      })
      .then((response) => {
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

        channel.bind("lighthouse-subscription", (payload) => {
          const result = payload.result;
          if (result && result.errors) {
            observer.onError(result.errors);
          } else if (result) {
            observer.onNext({
              data: result.data,
            });
          }
          if (!payload.more) {
            observer.onCompleted();
          }
        });
      });

    return {
      dispose: () => pusher.unsubscribe(channelName),
    };
  };
};

const fetchOperation = (operation, variables, cacheConfig) => {
  const bodyValues = {
    variables,
    query: operation.text,
    operationName: operation.name,
  };

  return fetch(`${API_LOCATION}/graphql`, {
    method: "POST",
    opts: {
      credentials: "include",
    },
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: BEARER_TOKEN,
    },
    body: JSON.stringify(bodyValues),
  });
};

const fetchQuery = (operation, variables, cacheConfig) => {
  return fetchOperation(operation, variables, cacheConfig).then((response) => {
    return response.json();
  });
};

const subscriptionHandler = createHandler({
  pusher: pusherClient,
  fetchOperation: fetchOperation,
});

const network = Network.create(fetchQuery, subscriptionHandler);

export const environment = new Environment({
  network,
  store: new Store(new RecordSource()),
});
```

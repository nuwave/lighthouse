# Filtering Subscriptions

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

use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

final class PostUpdatedSubscription extends GraphQLSubscription
{
    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        // Clients arguments when subscribing
        $args = $subscriber->args;

        // Ensure that the Post ($root) id matches
        // the requested `post_id`
        return $root->id == $args['post_id'];
    }
}
```

## Only To Others

When building an application that utilizes event broadcasting, you may occasionally need to broadcast an event to all subscribers of a channel except for the current user.
You may accomplish this using the filter function, this following snippet is equivalent to [the `toOthers()` method from Laravel's broadcast helper](https://laravel.com/docs/9.x/broadcasting#only-to-others).

```php
namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

final class PostUpdatedSubscription extends GraphQLSubscription
{
    /**
     * Filter which subscribers should receive the subscription.
     */
    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        // Filter out the sender
        return $subscriber->socket_id !== request()->header('X-Socket-ID');
    }
}
```

When you initialize a Laravel Echo instance, a socket ID is assigned to the connection.
If you are using a global [Axios](https://github.com/mzabriskie/axios) instance to make HTTP requests from your JavaScript application, the socket ID will automatically be attached to every outgoing request in the `X-Socket-ID` header.
Then, you can access that in your filter function.

If you are not using a global Axios instance, you will need to manually configure your JavaScript application to send the `X-Socket-ID` header with all outgoing requests.
You may retrieve the socket ID using the `Echo.socketId()` method:

```js
const socketId = Echo.socketId();
```

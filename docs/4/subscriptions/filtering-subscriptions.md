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

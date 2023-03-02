# Defining Fields

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
namespace App\GraphQL\Subscriptions;

use App\User;
use App\Post;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class PostUpdated extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        $user = $subscriber->context->user;
        $author = User::find($subscriber->args['author']);

        return $user->can('viewPosts', $author);
    }

    /**
     * @param  \App\Post  $root
     */
    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        $user = $subscriber->context->user;

        // Don't broadcast the subscription to the same
        // person who updated the post.
        return $root->updated_by !== $user->id;
    }

    public function encodeTopic(Subscriber $subscriber, string $fieldName): string
    {
        // Create a unique topic name based on the `author` argument
        return Str::snake($fieldName).':'.$subscriber->args['author'];
    }

    /**
     * @param  \App\Post  $root
     */
    public function decodeTopic(string $fieldName, mixed $root): string
    {
        // Decode the topic name if the `encodeTopic` has been overwritten.
        $author_id = $root->author_id;

        return Str::snake($fieldName).':'.$author_id;
    }

    /**
     * @param  \App\Post  $root
     */
    public function resolve(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Post
    {
        // Optionally manipulate the `$root` item before it gets broadcasted to
        // subscribed client(s).
        $root->load(['author', 'author.achievements']);

        return $root;
    }
}
```

If the default namespaces are not working with your application structure
or you want to be more explicit, you can use the [@subscription](../api-reference/directives.md#subscription)
directive to point to a different class.

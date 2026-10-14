# The N+1 Query Problem

A common performance pitfall that comes with the nested nature of GraphQL queries is the so-called N+1 query problem.

Let’s imagine we want to fetch a list of posts, and for each post, we want to add on the name of the associated author:

```graphql
{
  posts {
    title
    author {
      name
    }
  }
}
```

Following a naive execution strategy, Lighthouse would first query a list of posts, then loop over that list and resolve the individual fields.
The associated author for each post would be lazily loaded, querying the database once per post.

## Eager Loading Relationships

When dealing with Laravel relationships, [eager loading](https://laravel.com/docs/eloquent-relationships#eager-loading) is commonly used to alleviate the N+1 query problem.

You can leverage eager loading by informing Lighthouse of the relationships between your models, using directives such as [@belongsTo](../api-reference/directives.md#belongsto), [@hasMany](../api-reference/directives.md#hasmany) and [@with](../api-reference/directives.md#with).

```graphql
type Post {
  title: String!
  author: User! @belongsTo
}

type User {
  name: String!
  posts: [Post!]! @hasMany
}
```

Under the hood, Lighthouse will batch the relationship queries together in a single database query.

If you require a relation to be loaded for some field.
Do not wish to return the relationship itself, you can use the [@with](../api-reference/directives.md#with) directive.

## Custom Batch Loaders

In the following example, the `User` model is associated with multiple posts, but the posts are part of an external service.

```graphql
type User {
  id: ID!
  posts: [Post!]! # Not a relation, so we can not use @hasMany
}

type Post { # Not a model
  title: String!
}
```

Since we have multiple users, `User.posts` would be resolved multiple times in the following example query:

```graphql
{
  users {
    posts {
      title
    }
  }
}
```

We want to have a batch loader for `User.posts`, since it loads posts from a third party and the call to fetch them is slow when run sequentially.
This is assuming the posts service offers a method to query posts for multiple users in one call.

In order for Lighthouse to perform batch loading, it needs to group fields that are on the same level in the query tree, but nested under different indices.
When looking at the query path from `posts`, they may look like:

- `users.0.posts`
- `users.1.posts`

In order to combine them, you need to have a single stateful batch loader instance for `users.posts`.
Use `Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry` to resolve such instances.
The following code is the resolver for `User.posts`.
See [resolver precedence](../the-basics/fields.md#resolver-precedence) on how it could actually be assigned to a field.

```php
use GraphQL\Deferred;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;

function (User $user, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred {
    // Will always return the same instance, stored under the path users.posts
    $userPostsBatchLoader = BatchLoaderRegistry::instance(
        $resolveInfo->path,
        fn (): UserPostsBatchLoader => new UserPostsBatchLoader(),
    );

    // Promise to return the posts for the root resource and defer resolving them
    return $userPostsBatchLoader->load($user);
}
```

The implementation of `UserPostsBatchLoader` is up to you, the only important thing is that the resolver returns an instance of `GraphQL\Deferred`, see [webonyx/graphql-php docs](https://webonyx.github.io/graphql-php/data-fetching/#solving-n1-problem).
The following example illustrates some common patterns that may be found in a batch loader implementation:

```php
use GraphQL\Deferred;

final class UserPostsBatchLoader
{
    /**
     * Map from user IDs to users.
     *
     * @var array<int, \App\Models\User>
     */
    protected array $users = [];

    /**
     * Map from user IDs to posts.
     *
     * @var array<int, array<int, \App\Models\Post>>
     */
    protected array $results = [];

    /**
     * Marks when the actual batch loading happened.
     */
    protected bool $hasResolved = false;

    /**
     * Queue loading of posts for the given user.
     */
    public function load(User $user): Deferred
    {
        $this->users[$user->id] = $user;

        // The wrapped callable will run after load() has been called
        // with all users in the current query.
        return new Deferred(function () use ($user): array {
            // Ensure we only perform the actual loading exactly once.
            if (! $this->hasResolved) {
                $this->resolve();
            }

            return $this->results[$user->id];
        });
    }

    /**
     * Actually call out to the posts service and resolve them all at once.
     */
    protected function resolve(): void
    {
        $posts = PostsService::forUsers(array_keys($this->users));

        foreach ($posts as $post) {
            $this->results[$post->user_id][] = $post;
        }

        $this->hasResolved = true;
    }
}
```

You can also use a generic utility such as [DataLoaderPHP](https://github.com/overblog/dataloader-php) to build batch loaders.

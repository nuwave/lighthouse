# The N+1 Query Problem

A common performance pitfall that comes with the nested nature of GraphQL queries
is the so-called N+1 query problem.

Let’s imagine we want to fetch a list of posts, and for each post, we want to add on the
name of the associated author:

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

Following a naive execution strategy, Lighthouse would first query a list of posts,
then loop over that list and resolve the individual fields.
The associated author for each post would be lazily loaded, querying the database
once per post.

## Eager Loading Relationships

When dealing with Laravel relationships, [eager loading](https://laravel.com/docs/eloquent-relationships#eager-loading)
is commonly used to alleviate the N+1 query problem.

You can leverage eager loading by informing Lighthouse of the relationships between your models,
using directives such as [@belongsTo](../api-reference/directives.md#belongsto) and [@hasMany](../api-reference/directives.md#hasmany).

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

If you require a relation to be loaded for some field, but do not wish to return the relationship itself,
you can use the [@with](../api-reference/directives.md#with) directive.

## Resolving Batch Loader Instances

In order for Lighthouse to perform batch loading, it needs to group fields that are on the same level
in the query tree, but nested under different indices. For example, we are resolving the following query:

```graphql
{
  users {
    id
    posts {
      title
    }
  }
}
```

Imagine we want to have a batch loader for `User.posts`, since it loads posts from a third party and the
call to fetch them is slow when run sequentially. Since we have multiple users, this field
would be resolved multiple times. When looking at the query path from `posts`, they may look like:

- `users.0.posts`
- `users.1.posts`

In order to combine them, you need to have a single stateful batch loader instance for `users.posts`.
Use `\Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry` to resolve such instances.
The following code is the resolver for `User.posts` - it could point to it with [@field](../api-reference/directives.md#field),
or be implemented in a custom directive.

```php
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;

function (User $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): \GraphQL\Deferred {
    // Will always return the same instance, stored under the path users.posts
    $postsBatchLoader = BatchLoaderRegistry::instance(
        $resolveInfo->path,
        function (): UserPostsBatchLoader {
            return new UserPostsBatchLoader();
        }
    );

    // Promise to return the posts for the root resource and defer resolving them
    return $postsBatchLoader->load($root);
}
```

The implementation of `UserPostsBatchLoader` is up to you, the only important thing is that the resolver
returns an instance of `GraphQL\Deferred`.

## Batch Loader

`webonyx/graphql-php` allows deferring the actual resolution of a field until it is actually needed,
read more [in their documentation](https://webonyx.github.io/graphql-php/data-fetching/#solving-n1-problem).

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

## Data Loader

`webonyx/graphql-php` allows deferring the actual resolution of a field until it is actually needed,
read more [in their documentation](http://webonyx.github.io/graphql-php/data-fetching/#solving-n1-problem).

You can extend `\Nuwave\Lighthouse\Execution\DataLoader\BatchLoader` if you require custom batch loading.

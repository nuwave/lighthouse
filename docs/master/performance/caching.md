# Caching

If some fields in your schema are expensive or slow to compute, it can be
beneficial to cache their result. Use the [@cache](../api-reference/directives.md#cache)
directive to instruct Lighthouse to cache the result of a resolver.

The cache is created on the first request and is cached forever by default.
Use this for values that seldom change and take long to fetch/compute.

```graphql
type Query {
  highestKnownPrimeNumber: Int! @cache
}
```

Set an expiration time to invalidate the cache after the given number of seconds.

```graphql
type Query {
  temperature: Int! @cache(maxAge: 300)
}
```

You can limit the cache to the authenticated user making the request by marking it as private.
This makes sense for data that is specific to a certain user.

```graphql
type Query {
  todos: [ToDo!]! @cache(private: true)
}
```

## Clear cache

To enable this feature, you need to use a cache store that supports [cache tags](https://laravel.com/docs/cache#cache-tags)
and enable cache tags in `config/lighthouse.php`:

```php
    'cache' => [
        /*
         * Should the `@cache` directive use a tagged cache?
         */
        'tags' => true,
    ],
```

Now, you can place the [@clearCache](../api-reference/directives.md#clearcache) directive on
mutation fields. When they are queried, they will invalidate all cache entries associated with
a calculated tag. Depending on the effect of the mutation, you can clear different tags.

Update the cache associated with a given type without a specific ID:

> This does not invalidate cache entries related to the type _and_ ID.

```graphql
type Mutation {
  updateSiteStatistics(input: SiteInput!): Site @clearCache(type: "Site")
}
```

If your mutation affects only a certain ID, specify the source for that ID:

```graphql
type Mutation {
  updatePost(input: UpdatePostInput!): Post!
    @clearCache(type: "Post", idSource: { argument: "input.id" })
}
```

If your mutation affects multiple entities, you can use the result as the source of IDs:

```graphql
type Mutation {
  updatePosts(search: String!, newValue: Int!): [Post!]!
    @clearCache(type: "Post", idSource: { field: "*.id" })
}
```

If your mutation only affects a single field, you can clear tags that are specific for that:

```graphql
type Mutation {
  updatePost(id: ID!, title: String!): Post!
    @clearCache(type: "Post", idSource: { argument: "id" }, field: "title")
}
```

If your mutation affects multiple levels of cache, you can apply this directive repeatedly.

## Cache key

When generating a cached result for a resolver, Lighthouse produces a unique key for each type.
By default, Lighthouse will look for a field of type `ID` on the parent to generate the key
for a field with [@cache](../api-reference/directives.md#cache).

This directive allows to use a different field (i.e., an external API id):

```graphql
type GithubProfile {
  username: String @cacheKey
  repos: [Repository] @cache
}
```

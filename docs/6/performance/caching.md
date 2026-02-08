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
    /*
    |--------------------------------------------------------------------------
    | Cache Directive Tags
    |--------------------------------------------------------------------------
    |
    | Should the `@cache` directive use a tagged cache?
    |
    */
    'cache_directive_tags' => true,
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

## Implementing your own cache key generator

In one of your application service providers, bind the [`Nuwave\Lighthouse\Cache\CacheKeyAndTags.php`](https://github.com/nuwave/lighthouse/blob/master/src/Cache/CacheKeyAndTags.php)
interface to your cache key generator class:

```php
$this->app->bind(CacheKeyAndTags::class, YourOwnCacheKeyGenerator::class);
```

You can extend [`Nuwave\Lighthouse\Cache\CacheKeyAndTagsGenerator.php`](https://github.com/nuwave/lighthouse/blob/master/src/Cache/CacheKeyAndTagsGenerator.php)
to override certain methods, or implement the interface from scratch.

## HTTP Cache-Control header

**Experimental: not enabled by default, not guaranteed to be stable.**

Register the service provider `Nuwave\Lighthouse\CacheControl\CacheControlServiceProvider`,
see [registering providers in Laravel](https://laravel.com/docs/providers#registering-providers).

You can change the [`Cache-Control` header](https://developer.mozilla.org/de/docs/Web/HTTP/Headers/Cache-Control) of your response
regardless of [@cache](../api-reference/directives.md#cache)
by adding the [@cacheControl](../api-reference/directives.md#cachecontrol) directive to a field.
The directive can be defined on the field-level or type-level.
Note that field-level settings override type-level settings.

The final header settings are calculated based on these rules:

- `max-age` equals the lowest `maxAge` among all fields. If that value is 0, `no-cache` is used instead
- visibility is `public` unless the scope of a queried field is `PRIVATE`

The following defaults apply:

- non-scalar fields `maxAge` is 0
- root fields `maxAge` is 0 and `scope` is `PRIVATE`
- the directive default is prior to the field default

For more details check [Apollo](https://www.apollographql.com/docs/apollo-server/performance/caching/#calculating-cache-behavior).

Given the following example schema:

```graphql
type User {
  tasks: [Task!]! @hasMany @cacheControl(maxAge: 50, scope: PUBLIC)
}

type Company @cacheControl(maxAge: 40, scope: PUBLIC) {
  users: [User!]! @hasMany @cacheControl(maxAge: 25, scope: PUBLIC)
}

type Task {
  id: ID @cacheControl(maxAge: 10, scope: PUBLIC)
  name: String @cacheControl(maxAge: 0, inheritMaxAge: true)
  description: String @cacheControl
}

type Query {
  me: User! @auth @cacheControl(maxAge: 5, scope: PRIVATE)
  companies: [Company!]!
  publicCompanies: [Company!]! @cacheControl(maxAge: 15)
}
```

The Cache-Control headers for some queries will be:

```graphql
# Cache-Control header: max-age: 5, PRIVATE
{
  # 5, PRIVATE
  me {
    # 50, PUBLIC
    tasks {
      # 10, PUBLIC
      id
    }
  }
}

# Cache-Control header: no-cache, PRIVATE
{
  # 5, PRIVATE
  me {
    # 50, PUBLIC
    tasks {
      # 0, PUBLIC
      description
    }
  }
}

# Cache-Control header: no-cache, private
{
  # 40, PUBLIC
  companies {
    # 25, PUBLIC
    users {
      # 50, PUBLIC
      tasks {
        # 10, PUBLIC
        id
      }
    }
  }
}

# Cache-Control header: maxAge: 10, public
{
  # 15, PUBLIC
  publicCompanies {
    # 25, PUBLIC
    users {
      # 50, PUBLIC
      tasks {
        # 10, PUBLIC
        id
      }
    }
  }
}

# Cache-Control header: maxAge: 15, public
{
  # 15, PUBLIC
  publicCompanies {
    # 25, PUBLIC
    users {
      # 50, PUBLIC
      tasks {
        # 50, PUBLIC
        name
      }
    }
  }
}
```

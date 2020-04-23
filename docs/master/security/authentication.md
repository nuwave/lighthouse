# Authentication

You can use [standard Laravel mechanisms](https://laravel.com/docs/authentication)
to authenticate users of your GraphQL API. Stateless guards are recommended for most use cases.

## Global

As all GraphQL requests are served at a single HTTP endpoint, middleware added
through the `lighthouse.php` config will run for all queries against your server.

In most cases, your schema will have some publicly accessible fields and others
that require authentication. As multiple checks for authentication or permissions may be
required in a single request, it is convenient to attempt authentication once per request.

```php
    'route' => [
        'middleware' => [
            \Nuwave\Lighthouse\Support\Http\Middleware\AttemptAuthentication::class . ':api',
        ],
    ],
```

Note that the `AttemptAuthentication` middleware does _not_ protect any of your fields
by itself, decorate them with [`@guard`](../api-reference/directives.md#guard) as needed.

If you want to guard all your fields against unauthenticated access, you can simply add
Laravel's build-in auth middleware. Beware that this approach does not allow any GraphQL
operations for guest users, so you will have to handle login outside of GraphQL.

```php
'middleware' => [
    'auth:api',
],
```

## Guard selected fields

If you want to guard only selected fields, you can use the [`@guard`](../api-reference/directives.md#guard)
directive to require authentication for accessing them.

```graphql
type Query {
  profile: User! @guard
}
```

If you need to guard multiple fields, just use [`@guard`](../api-reference/directives.md#guard)
on a `type` or an `extend type` definition. It will be applied to all fields within that type.

```graphql
extend type Query @guard(with: ["api:admin"]) {
  adminInfo: Secrets
  nukeCodes: [NukeCode!]!
}
```

### Using Laravel Sanctum

Lighthouse will use your application's default guard, unless specified otherwise.
If you are using [Laravel Sanctum](https://laravel.com/docs/master/sanctum) only for your API,
you can set it when using `@guard`:

```graphql
type Query {
  profile: User! @guard(with: "sanctum")
}
```

## Get the current user

Lighthouse provides a really simple way to fetch the information of the currently authenticated user.
Just add a field that returns your `User` type and decorate it with the [`@auth`](../api-reference/directives.md#auth) directive.

```graphql
type Query {
  me: User @auth
}
```

Sending the following query will return the authenticated user's info
or `null` if the request is not authenticated.

```graphql
{
  me {
    name
    email
  }
}
```

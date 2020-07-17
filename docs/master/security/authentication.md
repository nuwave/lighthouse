# Authentication

You can use [standard Laravel mechanisms](https://laravel.com/docs/authentication)
to authenticate users of your GraphQL API.

## AttemptAuthentication middleware

As all GraphQL requests are served at a single HTTP endpoint, middleware added
through the `lighthouse.php` config will run for all queries against your server.

In most cases, your schema will have some publicly accessible fields and others
that require authentication. As multiple checks for authentication or permissions may be
required in a single request, it is convenient to attempt authentication once per request.

```php
    'route' => [
        'middleware' => [
            \Nuwave\Lighthouse\Support\Http\Middleware\AttemptAuthentication::class,
        ],
    ],
```

Note that the `AttemptAuthentication` middleware does _not_ protect your fields from unauthenticated
access, decorate them with [@guard](../api-reference/directives.md#guard) as needed.

If you want to guard all your fields against unauthenticated access, you can simply add
Laravel's build-in auth middleware. Beware that this approach does not allow any GraphQL
operations for guest users, so you will have to handle login outside of GraphQL.

```php
'middleware' => [
    'auth:api',
],
```

## Configure the guard

You can configure a default guard to use for authenticating GraphQL requests in `lighthouse.php`.

```php
    'guard' => 'api',
```

This setting is used whenever Lighthouse looks for an authenticated user, for example in directives
such as [@guard](../api-reference/directives.md#guard), or when applying the `AttempAuthentication` middleware.

Stateless guards are recommended for most use cases, such as the default `api` guard.

### Laravel Sanctum

If you are using [Laravel Sanctum](https://laravel.com/docs/master/sanctum) for your API, set the guard
to `sanctum` and register Sanctum's `EnsureFrontendRequestsAreStateful` as first middleware for Lighthouse's route.

```php
    'route' => [
        // ...
        'middleware' => [
            // ... other middleware

            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ],
    ],
    'guard' => 'sanctum',
```

Note that Sanctum requires you to send an CSRF token as [header](https://laravel.com/docs/csrf#csrf-x-csrf-token)
with all GraphQL requests, regardless of whether the user is authenticated or not.

When using [laravel-graphql-playground](https://github.com/mll-lab/laravel-graphql-playground), follow the [instructions
to add a CSRF token](https://github.com/mll-lab/laravel-graphql-playground#configure-session-authentication).

## Guard selected fields

If you want to guard only selected fields, you can use the [@guard](../api-reference/directives.md#guard)
directive to require authentication for accessing them.

```graphql
type Query {
  profile: User! @guard
}
```

If you need to guard multiple fields, just use [@guard](../api-reference/directives.md#guard)
on a `type` or an `extend type` definition. It will be applied to all fields within that type.

```graphql
extend type Query @guard
  adminInfo: Secrets
  nukeCodes: [NukeCode!]!
}
```

## Get the current user

Lighthouse provides a really simple way to fetch the information of the currently authenticated user.
Just add a field that returns your `User` type and decorate it with the [@auth](../api-reference/directives.md#auth) directive.

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

## Stateful Authentication Example

You can create or destroy a session with mutations instead of separate API endpoints (`/login`, `/logout`).
**This only works when Lighthouse's guard uses a session driver.**
Laravel's token based authentication does not allow logging in or out on the server side.

The implementation in the docs is only an example and may have to be adapted to your specific use case.

Add the following middleware to `config/lighthouse.php`:

```php
    'route' => [
        // ...
        'middleware' => [
            // Either those for plain Laravel:
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,

            // Or this one when using Laravel Sanctum:
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

            // ... other middleware
        ],
    ],
```

The `login` and `logout` might be defined and implement like this:

```graphql
type Mutation {
  "Log in to a new session and get the user."
  login(email: String!, password: String!): User!

  "Log out from the current session, showing the user one last time."
  logout: User @guard
}
```

```php
class Login
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): User
    {
        // Plain Laravel: Auth::guard()
        // Laravel Sanctum: Auth::guard(config('sanctum.guard', 'web'))
        $guard = ?;

        if( ! $guard->attempt($args)) {
            throw new Error('Invalid credentials.');
        }

        /**
         * Since we successfully logged in, this can no longer be `null`.
         *
         * @var \App\Models\User $user
         */
        $user = $guard->user();

        return $user;
    }
}
```

```php
class Logout
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): ?User
    {
        // Plain Laravel: Auth::guard()
        // Laravel Sanctum: Auth::guard(config('sanctum.guard', 'web'))
        $guard = ?;

        /** @var \App\Models\User|null $user */
        $user = $guard->user();
        $guard->logout();

        return $user;
    }
}
```

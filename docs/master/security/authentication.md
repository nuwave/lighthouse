# Authentication

## Global

You can use standard Laravel mechanisms to authenticate users of your GraphQL API.
Just add middleware trough your `lighthouse.php` configuration.
The [API Authentication](https://laravel.com/docs/api-authentication) is especially
suited because of its stateless nature.

As all GraphQL requests are served at a single HTTP endpoint, this will guard your
entire API against unauthenticated users.

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
extend type Query @guard(with: ["api:admin"]){
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

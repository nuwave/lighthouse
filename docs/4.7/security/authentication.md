# Authentication

You can use [standard Laravel mechanisms](https://laravel.com/docs/authentication)
to authenticate users of your GraphQL API. Stateless guards are recommended for most use cases.

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

## Apply auth middleware

Lighthouse allows you to configure global middleware that is run for every
request to your endpoint, but also define it on a per-field basis.

Use the [@middleware](../api-reference/directives.md#middleware) directive to apply Laravel middleware,
such as the `auth` middleware, to selected fields of your GraphQL endpoint.

```graphql
type Query {
  users: [User!]! @middleware(checks: ["auth:api", "custom"]) @all
}
```

If you need to apply middleware to multiple fields, just use [@middleware](../api-reference/directives.md#middleware)
on a `type` or an `extend type` definition.

```graphql
extend type Query @middleware(checks: ["auth:admin"]) {
  adminInfo: Secrets
  nukeCodes: [NukeCode!]!
}
```

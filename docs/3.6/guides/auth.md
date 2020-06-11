# Authentication & Authorization

### Get the current user

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

## Authorization

### Restrict access to fields

Lighthouse allows you to restrict field operations to a certain group of users.
Use the [@can](../api-reference/directives.md#can) directive to leverage [Laravel Policies](https://laravel.com/docs/authorization) for authorization.

### Apply auth middleware

Lighthouse allows you to configure global middleware that is run for every
request to your endpoint, but also define it on a per-field basis.

Use the [@middleware](../api-reference/directives.md#middleware) directive to apply Laravel middleware,
such as the `auth` middleware, to selected fields of your GraphQL endpoint.

```graphql
type Query {
  users: [User!]! @middleware(checks: ["auth:api", "custom"]) @all
}
```

If you need to apply middleware to a group of fields, you can put [@middleware](../api-reference/directives.md#middleware) on an Object type.

```graphql
extend type Query @group(middleware: ["auth:admin"]) {
  adminInfo: Secrets
  nukeCodes: [NukeCode!]!
}
```

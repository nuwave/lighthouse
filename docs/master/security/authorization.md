# Authorization

## Utilize the Viewer pattern

A common pattern is to allow users to only access entries that belong to them.
For example, a user may only be able to see notes they created.
You can utilize the nested nature of GraphQL queries to naturally limit access to such fields.

Begin with a field that represents the currently authenticated user, commonly called `me` or `viewer`.
You can resolve that field quite easily by using the [`@auth`](../api-reference/directives.md#auth) directive.

```graphql
type Query {
  me: User! @auth
}

type User {
  name: String!
}
```

Now, add related entities that are present as relationships onto the `User` type.

```graphql
type User {
  name: String!
  notes: [Note!]!
}

type Note {
  title: String!
  content: String!
}
```

Now, authenticated users can query for items that belong to them and are naturally
limited to seeing just those.

```graphql
{
  me {
    name
    notes {
      title
      content
    }
  }
}
```

## Restrict fields through policies

Lighthouse allows you to restrict field operations to a certain group of users.
Use the [@can](../api-reference/directives.md#can) directive to leverage [Laravel Policies](https://laravel.com/docs/authorization) for authorization.

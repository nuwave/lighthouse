# Schema

A schema defines the capabilities of a GraphQL server.
Much like a database schema, it describes the structure and the types your API can return.

## Types

Types are the primary building blocks of a GraphQL schema.
They define the capabilities of your API and the kind of data you can get from it.

```graphql
type User {
  id: ID!
  name: String!
  email: String!
  created_at: String!
  updated_at: String
}
```

## The Root Types

There can be up to 3 special _root types_ in a GraphQL schema.
They define the root fields that a query may have. While they are
all [Object Types](types.md#object-type), they differ in functionality.

### Query

Every GraphQL schema must have a `Query` type which contains the queries your API offers.
Think of queries as REST resources which can take arguments and return a fixed result.

```graphql
type Query {
  me: User
  users: [User!]!
  userById(id: ID): User
}
```

### Mutation

In contrast to the `Query` type, the fields of the `Mutation` type are
allowed to change data on the server.

```graphql
type Mutation {
  createUser(name: String!, email: String!, password: String!): User
  updateUser(id: ID, email: String, password: String): User
  deleteUser(id: ID): User
}
```

### Subscription

Rather than providing a single response, the fields of the `Subscription` type
return a stream of responses, with real-time updates.

```graphql
type Subscription {
  newUser: User
}
```

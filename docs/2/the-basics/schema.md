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

## Queries

Every GraphQL schema must have a `Query` type which contains the queries your API offers.
Think of queries as REST resources which can take arguments and return a fixed result.

```graphql
type Query {
  me: User
  users: [User!]!
  userById(id: ID): User
}
```

## Mutations

There is another special type called `Mutation`.
It works similar to the `Query` type, but it exposes operations that are
allowed to change data on the server.

```graphql
type Mutation {
  createUser(name: String!, email: String!, password: String!): User
  updateUser(id: ID, email: String, password: String): User
  deleteUser(id: ID): User
}
```

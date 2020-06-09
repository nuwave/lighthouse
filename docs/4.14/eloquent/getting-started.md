# Eloquent: Getting Started

Lighthouse makes it easy for you to perform queries and mutations on your Eloquent models.

## Defining Models

Eloquent models usually map directly to GraphQL types.

```graphql
type User {
  id: ID!
  name: String!
}
```

It is strongly advised to name the field that corresponds to your primary key `id`.
Popular client libraries such as Apollo provide out-of-the-box caching if you follow that convention.

## Retrieving Models

Instead of defining your own resolver manually, you can just rely on Lighthouse to build the Query for you.

```graphql
type Query {
  users: [User!]! @all
}
```

The [@all](../api-reference/directives.md#all) directive will assume the name of your model to be the same as
the return type of the Field you are trying to resolve and automatically uses Eloquent to resolve the field.

The following query:

```graphql
{
  users {
    id
    name
  }
}
```

Will return the following result:

```json
{
  "data": {
    "users": [
      { "id": 1, "name": "James Bond" },
      { "id": 2, "name": "Madonna" }
    ]
  }
}
```

## Pagination

You can leverage the [@paginate](../api-reference/directives.md#paginate) directive to
query a large list of models in chunks.

```graphql
type Query {
  posts: [Post!]! @paginate
}
```

The schema definition is automatically transformed to this:

```graphql
type Query {
  posts(first: Int!, page: Int): PostPaginator
}

type PostPaginator {
  data: [Post!]!
  paginatorInfo: PaginatorInfo!
}
```

And can be queried like this:

```graphql
{
  posts(first: 10) {
    data {
      id
      title
    }
    paginatorInfo {
      currentPage
      lastPage
    }
  }
}
```

## Adding Query Constraints

Lighthouse provides built-in directives to enhance your queries by giving
additional query capabilities to the client.

The following field definition allows you to fetch a single User by ID.

```graphql
type Query {
  user(id: ID! @eq): User @find
}
```

You can query this field like this:

```graphql
{
  user(id: 69) {
    name
  }
}
```

And, if a result is found, receive a result like this:

```json
{
  "data": {
    "user": {
      "name": "Chuck Norris"
    }
  }
}
```

## Create

The easiest way to create data on your server is to use the [@create](../api-reference/directives.md#create) directive.

```graphql
type Mutation {
  createUser(name: String!): User! @create
}
```

A create mutation will use the arguments passed to the field to create a new model instance:

```graphql
mutation {
  createUser(name: "Donald") {
    id
    name
  }
}
```

The newly created user is returned as a result:

```json
{
  "data": {
    "createUser": {
      "id": "123",
      "name": "Donald"
    }
  }
}
```

## Update

You can update a model with the [@update](../api-reference/directives.md#update) directive.

```graphql
type Mutation {
  updateUser(id: ID!, name: String): User @update
}
```

Since GraphQL allows you to update just parts of your data, it is best to have all arguments except `id` as optional.

```graphql
mutation {
  updateUser(id: "123", name: "Hillary") {
    id
    name
  }
}
```

```json
{
  "data": {
    "updateUser": {
      "id": "123",
      "name": "Hillary"
    }
  }
}
```

The update may fail to find the model you provided and return `null`:

```json
{
  "data": {
    "updateUser": null
  }
}
```

## Upsert

Use the [@upsert](../api-reference/directives.md#upsert) directive to update a model with
a given `id` or create it if it does not exist.

```graphql
type Mutation {
  upsertUser(id: ID!, name: String!, email: String): User @upsert
}
```

Since upsert can create or update your data, your input should mark the minimum required fields as non-nullable.
The `id` must always be required.

```graphql
mutation {
  upsertUser(id: "123", name: "Hillary") {
    id
    name
    email
  }
}
```

```json
{
  "data": {
    "upsertUser": {
      "id": "123",
      "name": "Hillary",
      "email": null
    }
  }
}
```

## Delete

Deleting models is a breeze using the [@delete](../api-reference/directives.md#delete) directive. Dangerously easy.

```graphql
type Mutation {
  deleteUser(id: ID!): User @delete
}
```

Simply call it with the ID of the user you want to delete.

```graphql
mutation {
  deleteUser(id: "123") {
    secret
  }
}
```

This mutation will return the deleted object, so you will have a last chance to look at the data. Use it wisely.

```json
{
  "data": {
    "deleteUser": {
      "secret": "Pink is my favorite color!"
    }
  }
}
```

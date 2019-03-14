# Objects
Object types define the resources of your API and are closely related to Eloquent models.
They must have a unique name and have a set of fields.

```graphql
type User {
  id: ID!
  name: String!
  email: String!
  created_at: String!
  updated_at: String
}

type Query {
  users: [User!]!
  user(id: ID!): User
}
```
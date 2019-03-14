# Inputs
Input types can be used to describe complex objects for field arguments.
Beware that while they look similar to Object Types, they behave differently:
The fields of an Input Type are treated similar to arguments.

```graphql
input CreateUserInput {
  name: String!
  email: String
}

type User {
  id: ID!
  name: String!
  email: String
}

type Mutation {
  createUser(input: CreateUserInput!): User
} 
```
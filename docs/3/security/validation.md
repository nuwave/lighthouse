# Validation

## Validating Arguments

Lighthouse allows you to use [Laravel's validation](https://laravel.com/docs/validation) for your
queries and mutations. The simplest way to leverage the built-in validation rules is to use the
[@rules](../api-reference/directives.md#rules) directive.

```graphql
type Mutation {
  createUser(
    name: String @rules(apply: ["required", "min:4"])
    email: String @rules(apply: ["email"])
  ): User
}
```

In the case of a validation error, Lighthouse will abort execution and return the validation messages
as part of the response.

```graphql
mutation {
  createUser(email: "hans@peter.xyz") {
    id
  }
}
```

```json
{
  "data": {
    "foo": null
  },
  "errors": [
    {
      "message": "validation",
      "locations": [
        {
          "line": 2,
          "column": 13
        }
      ],
      "extensions": {
        "validation": ["The name field is required."]
      }
    }
  ]
}
```

You can customize the error message for a particular argument.

```graphql
@rules(apply: ["max:140"], messages: { max: "Tweets have a limit of 140 characters"})
```

### Validating For Uniqueness

Using the [`unique`](https://laravel.com/docs/5.8/validation#rule-unique)
validation rule can be a bit tricky.

If the argument is nested within an input object, the argument path will not
match the column name, so you have to specify the column name explicitly.

```graphql
input CreateUserInput {
  email: String @rules(apply: ["unique:users,email_address"])
}
```

## Validating Arrays

When you are passing in an array as an argument to a field, you might
want to apply some validation on the array itself, using [@rulesForArray](../api-reference/directives.md#rules)

```graphql
type Mutation {
  makeIcecream(topping: [Topping!]! @rulesForArray(apply: ["max:3"])): Icecream
}
```

You can also combine this with [@rules](../api-reference/directives.md#rules) to validate
both the size and the contents of an argument array.
For example, you might require a list of at least 3 valid emails to be passed.

```graphql
type Mutation {
  attachEmails(
    email: [String!]! @rules(apply: ["email"]) @rulesForArray(apply: ["min:3"])
  ): File
}
```

## Using Custom Validation Rules

You can use [custom validation rules](https://laravel.com/docs/5.8/validation#custom-validation-rules) by passing class name.

```graphql
type Mutation {
  createUser(
    name: String @rules(apply: "required", "App\\Rules\\MyCustomRule")
    email: String @rules(apply: "required", "email", "unique:users,email")
    password: String @rules(apply: "required", "min:5") @bcrypt
   ): User
}
```

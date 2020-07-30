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

### Custom Error Messages

You can customize the error message for a particular argument.

```graphql
@rules(apply: ["max:140"], messages: { max: "Tweets have a limit of 140 characters"})
```

### Custom Validation Rules

Reference custom validation rules by their fully qualified class name.

```graphql
@rules(apply: ["App\\Rules\\MyCustomRule"])
```

## Validating Input Objects

Rules can be defined upon Input Object Values.

```graphql
input CreatePostInput {
  title: String @rules(apply: ["required"])
  content: String @rules(apply: ["min:50", "max:150"])
}
```

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

## Validate Fields

In some cases, validation rules are more complex and need to use entirely custom logic
or take multiple arguments into account.

To create a reusable validator that can be applied to fields, extend the base validation
directive `\Nuwave\Lighthouse\Schema\Directives\ValidationDirective`. Your custom directive
class should be located in one of the configured default directive namespaces, e.g. `App\GraphQL\Directives`.

```php
<?php

namespace App\GraphQL\Directives;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class UpdateUserValidationDirective extends ValidationDirective
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['required'],
            'name' => ['sometimes', Rule::unique('users', 'name')->ignore($this->args['id'], 'id')],
        ];
    }
}
```

Use it in your schema upon the field you want to validate.

```graphql
type Mutation {
  updateUser(id: ID, name: String): User @update @updateUserValidation
}
```

You can customize the messages for the given rules by implementing the `messages` function.

```php
    /**
     * @return array<string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'The chosen username is not available',
        ];
    }
```

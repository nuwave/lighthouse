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
  createUser(email: "hans@peter.xyz"){
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
        "validation": [
          "The name field is required."
        ]
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
    email: [String!]!
      @rules(apply: ["email"])
      @rulesForArray(apply: ["min:3"])
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
use Nuwave\Lighthouse\Validation\ValidateDirective;

class UpdateUserValidationDirective extends ValidateDirective
{
    /**
     * @return mixed[]
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
 * @return string[]
 */
public function messages(): array
{
    return [
        'name.unique' => 'The chosen username is not available',
    ];
}
```
## Validate Input Types

In cases where your validation becomes too complex and demanding, you want to have the power of PHP to actually do the 
complex validation. For example, accessing existing data in the database or validating complex combination of input 
values cannot be achieved with the examples above. This is where input type validation comes into play.

As an example, let's make sure the the following mutation is called with valid inputs:

```graphql
type Mutation {
  createUser(input: CreateUserInput! @spread): User @create
}
```

Input validation works by decorating the `input` type with the [`@rules`](../api-reference/directives.md#rules) directive:

```graphql
input CreateUserInput @rules {
  name: String!
  email: String!
  password: String!
}
```

This definition alone does not do anything though - we have to add a validation class that
corresponds to the `CreateUserInput` we defined. Let's create one with the artisan command:

    php artisan lighthouse:validator

The resulting class will be placed in your configured validator namespace. Let's go ahead
and define the validation rules for the input:

```php
namespace App\GraphQL\Validators;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Execution\InputValidator;

class CreateUserInputValidator extends InputValidator
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required'],
        ];
    }
}
```

Note that this gives you access to all kinds of programmatic validation rules that Laravel
provides. This can give you additional flexibility when you need it.

When updating for example a user, it is possible to obtain an instance of it by using `$this->model(User::class)`. This 
will return an instance of the user model based on it's primary key name. 

```graphql
type User {
    id: ID!
    name: String! 
    email: String!
}

input UpdateUserInput @rules {
    id: ID!
    name: String
    email: String 
}

type Mutation {
    updateUser(input: UpdateUserInput!): Material @update
}
```

```php
    use Illuminate\Validation\Rule;
    use Nuwave\Lighthouse\Execution\InputValidator;

class UpdateUserInputValidator extends InputValidator{
    public function rules() : array {
        $user = $this->model(User::class); 
        return [
            'email' => Rule::unique('users', 'email')->ignore($user) //note, the column is still required when validating graphql input 
        ];   
    }
}
```

The above validator will allow updating the user, but ignore the unique rule for its own record in the database.

```graphql
mutation{
    updateUser(input: {id: 1, email: "foo@bar.test", name: "foo"}){
       email
    }
}
```

The way how this works is that `$this->model()` looks at the field in the input that is the same as the key name
of the given model and will try to load in a model based on the value of the key. In most cases, this will be `id`.  

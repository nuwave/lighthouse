# Validation

Lighthouse allows you to use [Laravel's validation](https://laravel.com/docs/validation) for your
queries and mutations.

## Single Arguments

The simplest way to leverage the built-in validation rules is to use the
[@rules](../api-reference/directives.md#rules) directive.

```graphql
type Mutation {
  createUser(email: String @rules(apply: ["email"])): User
}
```

In the case of a validation error, Lighthouse will abort execution and return the validation messages
as part of the response.

```graphql
mutation {
  createUser(email: "foobar") {
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
        "validation": ["The email field must be a valid email."]
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

### Custom Validation Attribute

You can customize the attribute for a validation message.

```graphql
type Mutation {
  createUser(
    email: String @rules(apply: ["email"], attribute: "email address")
  ): User
}
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

Using the [`unique`](https://laravel.com/docs/validation#rule-unique)
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
want to apply some validation on the array itself, using [@rulesForArray](../api-reference/directives.md#rulesforarray)

```graphql
type Mutation {
  makeIcecream(
    "You may add up to three toppings to your icecream."
    topping: [Topping!] @rulesForArray(apply: ["max:3"])
  ): Icecream
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

## Validator Classes

In cases where your validation becomes too complex and demanding, you want to have the power of PHP to perform
complex validation. For example, accessing existing data in the database or validating the combination of input
values cannot be achieved with the examples above. This is where validator classes come into play.

Validator classes can be reused on field definitions or input types within your schema.
Use the [`@validator`](../api-reference/directives.md#validator) directive:

```graphql
input UpdateUserInput @validator {
  id: ID
  name: String
}
```

We need to back that with a validator class. Lighthouse uses a simple naming convention for validator classes,
just use the name of the input type and append `Validator`:

    php artisan lighthouse:validator UpdateUserInputValidator

The resulting class will be placed in your configured validator namespace. Let's go ahead
and define the validation rules for the input:

```php
namespace App\GraphQL\Validators;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class UpdateUserInputValidator extends Validator
{
    public function rules(): array
    {
        return [
            'id' => [
                'required'
            ],
            'name' => [
                'sometimes',
                Rule::unique('users', 'name')->ignore($this->arg('id'), 'id'),
            ],
        ];
    }
}
```

Note that this gives you access to all kinds of programmatic validation rules that Laravel
provides. This can give you additional flexibility when you need it.

You can customize the messages for the given rules by implementing the `messages` function:

```php
public function messages(): array
{
    return [
        'name.unique' => 'The chosen username is not available',
    ];
}
```

The `@validator` directive can also be used upon fields:

```graphql
type Mutation {
  updateUser(id: ID!, name: String): User @validator
}
```

In that case, Lighthouse will look for a validator class in a sub-namespace matching the parent type, in this case
that would be `Mutation`, so the default FQCN would be `App\GraphQL\Validators\Mutation\UpdateUserValidator`.

## Customize Query Validation Rules

By default, Lighthouse enables all default query validation rules from `webonyx/graphql-php`.
This covers fundamental checks, e.g. queried fields match the schema, variables have values of the correct type.

If you want to add custom rules or change which ones are used, you can bind a custom implementation
of the interface `\Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules` through a service provider.

```php
use Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules;

class MyCustomRulesProvider implements ProvidesValidationRules {}

$this->app->bind(ProvidesValidationRules::class, MyCustomRulesProvider::class);
```

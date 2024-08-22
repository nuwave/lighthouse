# Validation

Lighthouse allows you to use [Laravel's validation](https://laravel.com/docs/validation)
for your queries and mutations.

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
    "createUser": null
  },
  "errors": [
    {
      "message": "Validation failed for the field [createUser].",
      "locations": [
        {
          "line": 2,
          "column": 13
        }
      ],
      "extensions": {
        "validation": {
          "email": ["The email field must be a valid email."]
        }
      }
    }
  ]
}
```

### Custom Error Messages

You can customize the error message for a particular argument.

```graphql
@rules(
    apply: ["max:280"],
    messages: [
        {
            rule: "max"
            message: "Tweets have a limit of 280 characters"
        }
    ]
)
```

### Custom Validation Attributes

You can customize the name of the attribute that will be included in the validation message.

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
both size and contents of an argument array.
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
Use the [@validator](../api-reference/directives.md#validator) directive:

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

final class UpdateUserInputValidator extends Validator
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

### Custom Validator Messages

You can customize the messages for the given rules by implementing the `messages` function:

```php
public function messages(): array
{
    return [
        'name.unique' => 'The chosen username is not available',
    ];
}
```

### Custom Validation Attributes

You can customize the name of attributes that will be included in the validation message.

```php
public function attributes(): array
{
    return ['name' => 'username'];
}
```

### Validator For Fields

The `@validator` directive can also be used upon fields:

```graphql
type Mutation {
  updateUser(id: ID!, name: String): User @validator
}
```

In that case, Lighthouse will look for a validator class in a sub-namespace matching the parent type, in this case
that would be `Mutation`, so the default FQCN would be `App\GraphQL\Validators\Mutation\UpdateUserValidator`.

### Validator For Nested Inputs

Use multiple validators for complex inputs that include nested input objects. This ensures
they do not grow too complex and can be composed freely.

## Caveats

### No Mutating Rules

Validation rules that mutate the given input values are _not_ supported:

- `exclude_if`
- `exclude_unless`

### References

References are resolved relative to the argument or input field that rules are defined upon:

```graphql
type Mutation {
  foo(bar: Int, input: FooInput): ID
}

input FooInput {
  bar: Int
  notBar: Int @rules(apply: ["different:bar"])
}
```

The following mutation would pass validation, because `notBar` references the `bar` field of `FooInput`
and thus its value `1` is compared to the value `2` - which is different:

```graphql
mutation {
  foo(bar: 1, input: { bar: 2, notBar: 1 })
}
```

### Custom Rules With References

When creating custom validation rules with references, you need to tell Lighthouse
which parameters are references, so it can add the full argument path:

```graphql
input FooInput {
  foo: Int
  bar: Int @rules(apply: ["with_reference:equal_field,0,foo"])
}
```

In this example, `equal_field` is a custom rule that checks if the argument
is the same as the one referenced by the parameter.

The parameters to `with_reference` are:

1. Name of the custom rule
2. Indexes of the custom rule parameter that should be treated as a reference.
   Specify multiple indexes separated by `_`.
3. The parameters for the custom rule

If you are using custom rule classes, implement `WithReferenceRule::setArgumentPath()`.
Lighthouse will call this method with the argument path leading up to the validated argument before validation runs.

### Comparisons

If you need to validate the size of an integer, you need to add the
`integer` validation rule before:

```graphql
type Mutation {
  drinkCoffee(cups: Int! @rules(apply: ["integer", "max:3"])): Energy
}
```

Rules that reference other fields work strictly function as such.
For example, it is not possible to use `gt` to compare against a literal value,
use `min` instead:

```graphql
type Mutation {
  bakePizza(
    dough: Int @rules(apply: ["integer", "gt:water"])
    water: Int @rules(apply: ["integer", "min:2"])
  ): User
}
```

## Customize Query Validation Rules

By default, Lighthouse enables all default query validation rules from `webonyx/graphql-php`.
This covers fundamental checks, e.g. queried fields match the schema, variables have values of the correct type.

If you want to add custom rules or change which ones are used, you can bind a custom implementation
of the interface `\Nuwave\Lighthouse\Support\Contracts\ProvidesCacheableValidationRules` through a service provider.

```php
use Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules;

final class MyCustomRulesProvider implements ProvidesCacheableValidationRules {}

$this->app->bind(ProvidesCacheableValidationRules::class, MyCustomRulesProvider::class);
```

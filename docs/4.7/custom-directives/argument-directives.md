# Argument Directives

Argument directives can be applied to a [InputValueDefinition](https://graphql.github.io/graphql-spec/June2018/#InputValueDefinition).

As arguments may be contained within a list in the schema definition, you must specify
what your argument should apply to in addition to its function.

- If it applies to the individual items within the list,
  implement the [`\Nuwave\Lighthouse\Support\Contracts\ArgDirective`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ArgDirective.php) interface.
- Else, if it should apply to the whole list,
  implement the [`\Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ArgDirectiveForArray.php) interface.

You must implement exactly one of those two interfaces in order for an argument directive to work.

## ArgTransformerDirective

An [`\Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective`](https://github.com/nuwave/lighthouse/blob/master/src/Support/Contracts/ArgTransformerDirective.php)
takes an incoming value an returns a new value.

Let's take a look at the built-in `@trim` directive.

```php
<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

class TrimDirective implements ArgTransformerDirective
{
    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'trim';
    }

    /**
     * Remove whitespace from the beginning and end of a given input.
     *
     * @param  string  $argumentValue
     * @return mixed
     */
    public function transform($argumentValue): string
    {
        return trim($argumentValue);
    }
}
```

The `transform` method takes an argument which represents the actual incoming value that is given
to an argument in a query and is expected to transform the value and return it.

For example, if we have the following schema.

```graphql
type Mutation {
  createUser(name: String @trim): User
}
```

When you resolve the field, the argument will hold the "transformed" value.

```php
<?php

namespace App\GraphQL\Mutations;

use App\User;

class CreateUser
{
    public function __invoke($root, array $args): User
    {
        return User::create([
            // This will be the trimmed value of the `name` argument
            'name' => $args['name']
        ]);
    }
}
```

### Evaluation Order

Argument directives are evaluated in the order that they are defined in the schema.

```graphql
type Mutation {
  createUser(
    password: String @trim @rules(apply: ["min:10,max:20"]) @bcrypt
  ): User
}
```

In the given example, Lighthouse will take the value of the `password` argument and:

1. Trim any whitespace
1. Run validation on it
1. Encrypt the password via `bcrypt`

## ArgBuilderDirective

An [`\Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective`](https://github.com/nuwave/lighthouse/blob/master/src/Support/Contracts/ArgBuilderDirective.php)
directive allows using arguments passed by the client to dynamically
modify the database query that Lighthouse creates for a field.

Currently, the following directives use the defined filters for resolving the query:

- `@all`
- `@paginate`
- `@find`
- `@first`
- `@hasMany` `@hasOne` `@belongsTo` `@belongsToMany`

Take the following schema as an example:

```graphql
type User {
  posts(category: String @eq): [Post!]! @hasMany
}
```

Passing the `category` argument will select only the user's posts
where the `category` column is equal to the value of the `category` argument.

So let's take a look at the built-in `@eq` directive.

```php
<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class EqDirective extends BaseDirective implements ArgBuilderDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'eq';
    }

    /**
     * Apply a simple "WHERE = $value" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param  mixed $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value)
    {
        return $builder->where(
            $this->directiveArgValue('key', $this->definitionNode->name->value),
            $value
        );
    }
}
```

The `handleBuilder` method takes two arguments:

- `$builder`
  The query builder for applying the additional query on to.
- `$value`
  The value of the argument value that the `@eq` was applied on to.

If you want to use a more complex value for manipulating a query,
you can build a `ArgBuilderDirective` to work with lists or nested input objects.
Lighthouse's [`@whereBetween`](../api-reference/directives.md#wherebetween) is one example of this.

```graphql
type Query {
  users(createdBetween: DateRange @whereBetween(key: "created_at")): [User!]!
    @paginate
}

input DateRange {
  from: Date!
  to: Date!
}
```

## ArgManipulator

An [`\Nuwave\Lighthouse\Support\Contracts\ArgManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ArgManipulator.php)
directive can be used to manipulate the schema AST.

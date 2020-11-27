# Custom Directives

Lighthouse provides general purpose server side directives.

As you grow your GraphQL schema, you may find the need for more specialized functionality.
Learn how you can abstract logic in a composable and reusable manner by using custom directives.

## Directive Class Naming Convention

Directives are implemented as PHP classes, each directive available
in the schema corresponds to a single class.

The class name of a directive must follow the following pattern:

    <Directive name in StudlyCase>Directive

For example the class name of directive `@fooBar` must be `FooBarDirective`.

## Directive Interfaces

Every directive must implement the interface [`\Nuwave\Lighthouse\Support\Contracts\Directive`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/Directive.php).
It contains a single function `name` to specify the name the directive has in the schema.

By itself though, directives that implement just this base interface do not do anything.
Depending on what your directive should do, you can pick one or more of the provided
directive interfaces to add functionality. They serve as the point of contact to Lighthouse.

Depending on which interfaces you implement, a directive can be applied to different parts
of the schema, according to the [specified directive location](https://facebook.github.io/graphql/June2018/#DirectiveLocation).

## Node Directives

These directives can generally be applied to [type definitions](../the-basics/types.md) in the schema.

### NodeManipulator

The [`\Nuwave\Lighthouse\Support\Contracts\NodeManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/NodeManipulator.php)
interface can be used to manipulate the AST.

### NodeMiddleware

The [`\Nuwave\Lighthouse\Support\Contracts\NodeMiddleware`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/NodeMiddleware.php)
interface allows access to an AST node as it is converted to an executable type.

### NodeResolves

The [`\Nuwave\Lighthouse\Support\Contracts\NodeResolves`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/NodeResolver.php)
interface can be used for custom conversion from AST values to an executable type.

## Field Directives

Field directives can be applied to any [FieldDefinition](https://graphql.github.io/graphql-spec/June2018/#FieldDefinition)

### FieldResolver

Perhaps the most important directive interface, a [`\Nuwave\Lighthouse\Support\Contracts\FieldResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/FieldResolver.php)
let's you add a resolver for a field through a directive.

It can be a great way to reuse resolver logic within a schema.

### FieldMiddleware

A [`\Nuwave\Lighthouse\Support\Contracts\FieldMiddleware`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/FieldMiddleware.php) directive allows you
to wrap around the field resolver, just like [Laravel Middleware](https://laravel.com/docs/middleware).

You may use it both to handle incoming values before reaching the final resolver
as well as the outgoing result of resolving the field.

### FieldManipulator

An [`\Nuwave\Lighthouse\Support\Contracts\FieldManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/FieldManipulator.php)
directive can be used to manipulate the schema AST.

## Argument Directives

Argument directives can be applied to a [InputValueDefinition](https://graphql.github.io/graphql-spec/June2018/#InputValueDefinition).

As arguments may be contained within a list in the schema definition, you must specify
what your argument should apply to in addition to its function.

- If it applies to the individual items within the list,
  implement the [`\Nuwave\Lighthouse\Support\Contracts\ArgDirective`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ArgDirective.php) interface.
- Else, if it should apply to the whole list,
  implement the [`\Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ArgDirectiveForArray.php) interface.

You must implement exactly one of those two interfaces in order for an argument directive to work.

### ArgValidationDirective

An [`\Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective`](https://github.com/nuwave/lighthouse/blob/master/src/Support/Contracts/ArgValidationDirective.php)
may be used to return custom rules and messages to use for validation of an argument.

### ArgTransformerDirective

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

class CreateUser
{
    public function resolve($root, array $args)
    {
        return User::create([
            // This will be the trimmed value of the `name` argument
            'name' => $args['name']
        ]);
    }
}
```

#### Evaluation Order

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

### ArgBuilderDirective

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

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
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
  users(createdBetween: [Date!]! @whereBetween(key: "created_at")): [User!]!
    @paginate
}
```

### ArgManipulator

An [`\Nuwave\Lighthouse\Support\Contracts\ArgManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ArgManipulator.php)
directive can be used to manipulate the schema AST.

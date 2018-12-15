# Defining Directives

Lighthouse provides various convenient server side directives that can be applied to a lots of generic use cases.
However you are free to create your own directives depending upon your needs. 

## Directive Types

There are 3 different levels of directives in Lighthouse.

* [Node Directive](#node-directive)
* [Field Directive](#field-directive)
* [Argument Directive](#argument-directive)

They are applied to different places where associate with the [DirectiveLocation](https://facebook.github.io/graphql/June2018/#DirectiveLocation).

## Directive Class Naming Convention
All directives must have their class name to be:

```
<Study case of directive name> + "Directive"
```

For example the class name of directive `@foo_bar` must be `FooBarDirective`. 

## Node Directive

## Field Directive

## Argument Directive

Argument directives are applied to the [InputValueDefinition](https://facebook.github.io/graphql/June2018/#InputValueDefinition).

There are 2 types of argument directives in Lighthouse.

* [ArgTransformerDirective](#argtransformerdirective)
* [ArgFilterDirective](#argfilterdirective)

### ArgTransformerDirective

The `ArgTransformerDirective` takes an incoming value an returns a new value. 

Let's take a look at the built-in `@trim` directive.

```php
<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

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
     * @param string $argumentValue
     *
     * @return mixed
     */
    public function transform($argumentValue): string
    {
        return trim($argumentValue);
    }
}
```

As you can see, to create an `ArgTransformerDirective` you should implement the `ArgTransformerDirective` interface.

This interface requires you to implement a method called `transform`.

The `transform` method takes an argument which represents the actual incoming value of the argument.

Apply the transformation then simply return the transformed value.

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
            // This will be the "trimed" value of the `name` argument
            'name' => $args['name']
        ]);
    }
}
```

### ArgFilterDirective

The `ArgFilterDirective` applies additional queries to those directives that are using the `Nuwave\Lighthouse\Execution\QueryFilter`. 

Currently, the following directives. 

* `@all`
* `@paginate`
* `@hasMany` `@hasOne` `@belongsTo` `@belongsToMany`

For example, if we have the following schema:

```graphql
type User {
 posts(category: String @eq(key: "cat")): [Post!]! @hasMany
}
```

as a result, it will select the user's posts where it's category(cat) is equal to the value of `category` argument.

So let's take a look at the built-in `@eq` directive.

```php
<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class EqDirective implements ArgFilterDirective
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
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param string                                                                   $columnName
     * @param mixed                                                                    $value
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function applyFilter($builder, string $columnName, $value)
    {
        return $builder->where($columnName, $value);
    }

    /**
     * Does this filter combine the values of multiple input arguments into one query?
     *
     * This is true for filter directives such as "whereBetween" that expects two
     * different input values, given as separate arguments.
     *
     * @return bool
     */
    public function combinesMultipleArguments(): bool
    {
        return false;
    }
}
```

The `applyFilter` method takes three arguments

* `$builder`  
The query builder for applying the additional query on to.
* `$columnName`  
The argument name by default, in our example the default value will be `'category'`.  
However you can specify a value explicitly by using the `key` argument of `@eq`.
As you can see, in our example we set the `key` to a string value 'cat', so the value of `$columnName` here is `'cat'`.
* `$value`  
The value of the argument value that the `@eq` was applied on to.

The `combinesMultipleArguments` method determine whether or not to combines multiple arguments.

Considering the following use case.

```graphql
type Query {
  posts(
    createdAfter: Date! @whereBetween(key: "created_at")
    createdBefore: String! @whereBetween(key: "created_at")
  ): [Post!]! @all
}
```

Where the `@whereBetween` directive must be applied to 2 arguments to get an array value that represents min and max values.

In such a case, you want to have the `combinesMultipleArguments` method returned `true` to gather all the values of all the same directives.

### Evaluation Order
Argument directives are evaluated by their written order.

Considering the following example.

```graphql
type Mutation {
  createUser(
    password: String @trim @rules(apply: ["min:10,max:20"]) @bcrypt
  ): User
}
```

Notice the order how the argument directives were written.

The evaluation process of the above example written in pseudo code can be:
 
```php
$trimedValue = trim($password);
// validate with the rules ["min:10,max:20"] ...
$finalArgumentValue = bcrypt($trimedValue);
```

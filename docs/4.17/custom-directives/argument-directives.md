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

Let's take a look at the built-in [@trim](../api-reference/directives.md#trim) directive.

```php
<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class TrimDirective extends BaseDirective implements ArgTransformerDirective, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Run the `trim` function on an input value.
"""
directive @trim on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Remove whitespace from the beginning and end of a given input.
     *
     * @param  string  $argumentValue
     * @return string
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
    password: String @trim @rules(apply: ["min:10,max:20"]) @hash
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

- [@all](../api-reference/directives.md#all)
- [@paginate](../api-reference/directives.md#paginate)
- [@find](../api-reference/directives.md#find)
- [@first](../api-reference/directives.md#first)
- [@hasMany](../api-reference/directives.md#hasmany)
- [@hasOne](../api-reference/directives.md#hasone)
- [@belongsTo](../api-reference/directives.md#belongsto)
- [@belongsToMany](../api-reference/directives.md#belongstomany)

Take the following schema as an example:

```graphql
type User {
  posts(category: String @eq): [Post!]! @hasMany
}
```

Passing the `category` argument will select only the user's posts
where the `category` column is equal to the value of the `category` argument.

So let's take a look at the built-in [@eq](../api-reference/directives.md#eq) directive.

```php
<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class EqDirective extends BaseDirective implements ArgBuilderDirective, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
directive @eq(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Apply a "WHERE = $value" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value)
    {
        return $builder->where(
            $this->directiveArgValue('key', $this->nodeName()),
            $value
        );
    }
}
```

The `handleBuilder` method takes two arguments:

- `$builder`
  The query builder for applying the additional query on to.
- `$value`
  The value of the argument value that [@eq](../api-reference/directives.md#eq) was applied on to.

If you want to use a more complex value for manipulating a query,
you can build a `ArgBuilderDirective` to work with lists or nested input objects.
Lighthouse's [@whereBetween](../api-reference/directives.md#wherebetween) is one example of this.

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

## ArgResolver

An [`\Nuwave\Lighthouse\Support\Contracts\ArgResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ArgResolver.php)
directive allows you to compose resolvers for complex nested inputs, similar to the way
that field resolvers are composed together.

For an in-depth explanation of the concept of composing arg resolvers,
read the [explanation of arg resolvers](../concepts/arg-resolvers.md).

## ArgManipulator

An [`\Nuwave\Lighthouse\Support\Contracts\ArgManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ArgManipulator.php)
directive can be used to manipulate the schema AST.

For example, you might want to add a directive that automagically derives the arguments
for a field based on an object type. A skeleton for this directive might look something like this:

```php
<?php

namespace App\GraphQL\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class ModelArgsDirective extends BaseDirective implements ArgManipulator, DefinedDirective
{
    /**
     * SDL definition of the directive.
     *
     * @return string
     */
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Automatically generates an input argument based on a type.
"""
directive @typeToInput(
    """
    The name of the type to use as the basis for the input type.
    """
    name: String!
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Manipulate the AST.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $argDefinition
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $parentField
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @return void
     */
    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        $typeName = $this->directiveArgValue('name');
        $type = $documentAST->types[$typeName];

        $input = $this->generateInputFromType($type);
        $argDefinition->name->value = $input->value->name;

        $documentAST->setTypeDefinition($input);
    }

    protected function generateInputFromType(ObjectTypeDefinitionNode $type): InputObjectTypeDefinitionNode
    {
        // TODO generate this type based on rules and conventions that work for you
    }
}
```

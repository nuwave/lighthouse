# Type Directives

These directives can generally be applied to [type definitions](../the-basics/types.md) in the schema.

> This is not limited to `type` but also includes `input`, `enum`, `union`, `interface` and `scalar` types.

## TypeManipulator

The [`\Nuwave\Lighthouse\Support\Contracts\TypeManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/TypeManipulator.php)
interface can be used to manipulate the AST from a type definition node.

## TypeMiddleware

The [`\Nuwave\Lighthouse\Support\Contracts\TypeMiddleware`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/TypeMiddleware.php)
interface allows access to an AST node as it is converted to an executable type.

## TypeResolver

The [`\Nuwave\Lighthouse\Support\Contracts\TypeResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/TypeResolver.php)
interface can be used for custom conversion from AST values to an executable type.

## Type Extension Directives

These directives can generally be applied to [type extensions](https://graphql.github.io/graphql-spec/June2018/#sec-Type-Extensions) in the schema.

## TypeExtensionManipulator

The [`\Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/TypeExtensionManipulator.php)
interface can be used to manipulate the AST from a type extension node.

# Node Directives

These directives can generally be applied to [type definitions](../the-basics/types.md) in the schema.

## NodeManipulator

The [`\Nuwave\Lighthouse\Support\Contracts\NodeManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/NodeManipulator.php)
interface can be used to manipulate the AST.

## NodeMiddleware

The [`\Nuwave\Lighthouse\Support\Contracts\NodeMiddleware`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/NodeMiddleware.php)
interface allows access to an AST node as it is converted to an executable type.

## NodeResolves

The [`\Nuwave\Lighthouse\Support\Contracts\NodeResolves`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/NodeResolver.php)
interface can be used for custom conversion from AST values to an executable type.

# Changing the resolver

The first priority when looking for a resolver is always given to `FieldResolver` directives.

After that, Lighthouse attempts to find a default resolver.

The interface [`\Nuwave\Lighthouse\Support\Contracts\ProvidesResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ProvidesResolver.php)
is expected to provide a resolver in case no resolver directive is defined for a field.

If the field is defined on the root `Query` or `Mutation` types,
Lighthouse's default implementation looks for a class with the capitalized name
of the field in the configured default location.

Non-root fields fall back to [webonyx's default resolver](http://webonyx.github.io/graphql-php/data-fetching/#default-field-resolver).
You may overwrite this by passing a `callable` to `\GraphQL\Executor\Executor::setDefaultFieldResolver`. 

When the field is defined on the root `Subscription` type, the [`\Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ProvidesSubscriptionResolver.php)
interface is used instead.
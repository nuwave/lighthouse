# Extending Lighthouse

Lighthouse offers various extension points which can be utilized by package developers
as well as end users.

## The Event System

Lighthouse offers a unified way of hooking into the complete execution lifecycle
through [Laravel's event system](https://laravel.com/docs/events).
You may use any Service Provider to register listeners.

You can find a complete list of all dispatched events [in the events API reference](../api-reference/events.md).

## Adding Directives

You can add your custom directives to Lighthouse by listening for the
[`RegisterDirectiveNamespaces`](../api-reference/events.md#registerdirectivenamespaces) event.

Check out [the test suite](https://github.com/nuwave/lighthouse/tree/master/tests/Integration/Events/RegisterDirectiveNamespacesTest.php)
for an example of how this works.

## Changing the default resolver

The first priority when looking for a resolver is always given to `FieldResolver` directives.

After that, Lighthouse attempts to find a default resolver.

The interface [`\Nuwave\Lighthouse\Support\Contracts\ProvidesResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ProvidesResolver.php)
is expected to provide a resolver in case no resolver directive is defined for a field.

If the field is defined on the root `Query` or `Mutation` types,
Lighthouse's default implementation looks for a class with the capitalized name
of the field in the configured default location and calls its `__invoke` method.

Non-root fields fall back to [webonyx's default resolver](https://webonyx.github.io/graphql-php/data-fetching/#default-field-resolver).
You may overwrite this by passing a `callable` to `\GraphQL\Executor\Executor::setDefaultFieldResolver`.

When the field is defined on the root `Subscription` type, the [`\Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ProvidesSubscriptionResolver.php)
interface is used instead.

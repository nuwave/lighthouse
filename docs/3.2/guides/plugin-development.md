# Plugin Development

Lighthouse is able to be extended in a lot of places. Plugin developers
may utilize this to offer users extra functionality that is not available in the core.

## Guidelines

- Try not to change core behaviour. Warn your users if you do.
- Consider improving the extensibility of Lighthouse with a PR instead of doing workarounds.
- Add your plugin to the Resources page once it is done.

## Events

Lighthouse offers a unified way of hooking into the complete execution lifecycle
through [Laravel's event system](https://laravel.com/docs/events).
You may use the Service Provider of your package to register listeners.

You can find a complete list of all dispatched events [in the events API reference](../api-reference/events.md).

### Add schema definitions

You might want to provide some additional types to the schema. The preferred way to
do this is to listen for the [`BuildSchemaString`](../api-reference/events.md#buildschemastring) event.

Check out [the test suite](https://github.com/nuwave/lighthouse/tree/master/tests/Integration/Events/BuildSchemaStringTest.php)
for an example of how this works.

### Add custom directives

You can add your custom directives to Lighthouse by listening for the [`RegisterDirectiveNamespaces`](../api-reference/events.md#registerdirectivenamespaces) event.

Check out [the test suite](https://github.com/nuwave/lighthouse/tree/master/tests/Integration/Events/RegisterDirectiveNamespacesTest.php)
for an example of how this works.

## Change the default resolver

The first priority when looking for a resolver is always given to `FieldResolver` directives.

After that, Lighthouse attempts to find a default resolver.

The interface [`\Nuwave\Lighthouse\Support\Contracts\ProvidesResolver`](../../../src/Support/Contracts/ProvidesResolver.php)
is expected to provide a resolver in case no resolver directive is defined for a field.

If the field is defined on the root `Query` or `Mutation` types,
Lighthouse's default implementation looks for a class with the capitalized name
of the field in the configured default location.

Non-root fields fall back to [webonyx's default resolver](http://webonyx.github.io/graphql-php/data-fetching/#default-field-resolver).
You may overwrite this by passing a `callable` to `\GraphQL\Executor\Executor::setDefaultFieldResolver`.

When the field is defined on the root `Subscription` type, the [`\Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver`](../../../src/Support/Contracts/ProvidesSubscriptionResolver.php)
interface is used instead.

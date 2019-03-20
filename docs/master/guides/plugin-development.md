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

# Plugin Development

Lighthouse is able to be extended in a lot of places. Plugin developers
may utilize this to offer users extra functionality that is not available in the core.

## Guidelines

- Try not to change core behaviour. If you really have to, make it really explicit.
- Consider improving the extensibility of Lighthouse with a PR instead of doing workarounds.
- Add your plugin to the Resources page once it is done.

## Add schema definitions

You might want to provide some additional types to the schema. The preferred way to
do this is to listen for the `BuildSchemaString` event. It is sent before Lighthouse parses
the schema string.

Check out [the test suite](https://github.com/nuwave/lighthouse/tree/master/tests/Integration/Events/BuildSchemaStringTest.php)
for an example of how this works.

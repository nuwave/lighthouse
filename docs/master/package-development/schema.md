# Extending The Schema


## Add schema definitions

You might want to provide some additional types to the schema. The preferred way to
do this is to listen for the [`BuildSchemaString`](../api-reference/events.md#buildschemastring) event.

Check out [the test suite](https://github.com/nuwave/lighthouse/tree/master/tests/Integration/Events/BuildSchemaStringTest.php)
for an example of how this works.
# Feature Toggles

Lighthouse allows you to conditionally show or hide elements of your schema.

## @show and @hide

The directives [@show](../api-reference/directives.md#show) and [@hide](../api-reference/directives.md#hide)
work in a similar way, but are logical opposites of each other.

For example, you might want to limit an experimental new field to test environments.
[@show](../api-reference/directives.md#show) is most suitable for this:

```graphql
type Query {
  testInformation: String! @show(env: ["integration", "staging"])
}
```

Another example would be a field that should be available in every environment but production.
In this case, [@hide](../api-reference/directives.md#hide) fits better:

```graphql
type Query {
  debugInformation: String! @hide(env: ["production"])
}
```

## Interaction With Schema Cache

[@show](../api-reference/directives.md#show) and [@hide](../api-reference/directives.md#hide) work by manipulating the schema.
This means that when using their `env` option, the inclusion or exclusion of elements depends on the value
of `app()->environment()` at the time the schema is built and not update on later environment changes.
If you are pre-generating your schema cache, make sure to match the environment to your deployment target.

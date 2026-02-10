# Feature Toggles

Lighthouse allows you to conditionally show or hide elements (fields, types, arguments or input fields) of your schema.

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

## @feature

The [@feature](../api-reference/directives.md#feature) directive allows to include fields, types, arguments, or input fields in the schema
depending on whether a [Laravel Pennant](https://laravel.com/docs/pennant) feature is active.

For example, you might want a new experimental field only to be available when the according feature is active:

```graphql
type Query {
  experimentalField: String! @feature(name: "new-api")
}
```

In this case, `experimentalField` will only be included when the `new-api` feature is active.

Another example would be to only include a field when the feature is inactive:

```graphql
type Query {
  deprecatedField: String! @feature(name: "new-api", when: "INACTIVE")
}
```

When using [class based features](https://laravel.com/docs/pennant#class-based-features),
the fully qualified class name must be used as the value for the `name` argument:

```graphql
type Query {
  experimentalField: String! @feature(name: "App\\Features\\NewApi")
}
```

## Conditional Type Inclusion

When you conditionally include a type using [@show](../api-reference/directives.md#show), [@hide](../api-reference/directives.md#hide) or [@feature](../api-reference/directives.md#feature),
any fields using it must also be conditionally included.
If the type is omitted but still used somewhere, the schema will be invalid.

```graphql
type ExperimentalType @feature(name: "new-api") {
  field: String!
}

type Query {
  experimentalField: ExperimentalType @feature(name: "new-api")
}
```

## Interaction With Schema Cache

[@show](../api-reference/directives.md#show) and [@hide](../api-reference/directives.md#hide) work by manipulating the schema.
This means that when using their `env` option, the inclusion or exclusion of elements depends on the value
of `app()->environment()` at the time the schema is built and not update on later environment changes.
If you are pre-generating your schema cache, make sure to match the environment to your deployment target.

The same goes for [@feature](../api-reference/directives.md#feature). Whether a field is included in the schema will be
based on the state of a feature at the time the schema is built. In addition, if you are pre-generating your schema cache,
you will only be able to use features that support [nullable scopes](https://laravel.com/docs/pennant#nullable-scope),
as there won't be an authenticated user to check the feature against.

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

You can find and compare releases at the [GitHub release page](https://github.com/nuwave/lighthouse/releases).

## Unreleased

### Added

- Add `AttemptAuthentication` middleware to optionally log in users and delegate access guards
  to the field level https://github.com/nuwave/lighthouse/pull/1197
- Add artisan command `lighthouse:directive` to add directive class https://github.com/nuwave/lighthouse/pull/1240

### Fixed

- Eager load nested relations using the `@with` directive https://github.com/nuwave/lighthouse/pull/1068 
- Avoid infinite loop with empty namespace in generator commands https://github.com/nuwave/lighthouse/pull/1245
- Automatically register `TestingServiceProvider` for `@mock` when running unit tests https://github.com/nuwave/lighthouse/pull/1244

## 4.10.2

### Fixed

- Ensure subscription routes are named uniquely https://github.com/nuwave/lighthouse/pull/1231

### Changed

- Throw user readable `Error` instead of `ModelNotFoundException` when model is not found in `@can` https://github.com/nuwave/lighthouse/pull/1225

## 4.10.1

### Fixed

- Fix Laravel version detection for Lumen https://github.com/nuwave/lighthouse/pull/1224

## 4.10.0

### Added

- Access nested inputs with dot notation using the `find` option of `@can` https://github.com/nuwave/lighthouse/pull/1216
- Add `@hash` directive which uses Laravel's hashing configuration https://github.com/nuwave/lighthouse/pull/1200
- Add option `passOrdered` to `@method` to pass just the arguments as ordered parameters https://github.com/nuwave/lighthouse/pull/1208
- Add support to extend `input`, `interface` and `enum` types https://github.com/nuwave/lighthouse/pull/1203
- Implement `streamGraphQL()` helper in `\Nuwave\Lighthouse\Testing\MakesGraphQLRequestsLumen` https://github.com/nuwave/lighthouse/pull/1222
- Support Laravel 7 https://github.com/nuwave/lighthouse/pull/1219

### Deprecated

- Remove `@bcrypt` in favour of `@hash` https://github.com/nuwave/lighthouse/pull/1200
- `@method` will call the underlying method with the arguments as ordered parameters instead
  of the full resolver arguments https://github.com/nuwave/lighthouse/pull/1208

## 4.9.0

### Added

- Add optional `columnsEnum` argument to the `@whereConditions`, `@whereHasConditions`
  and `@orderBy` directives https://github.com/nuwave/lighthouse/pull/1150
- Exclude or include trashed models in `@can` when `@forceDelete` or `@restore` are used
  so the client does not have to filter explicitly https://github.com/nuwave/lighthouse/pull/1157
- Add test trait `\Nuwave\Lighthouse\Testing\MakesGraphQLRequestsLumen` for usage
  with Lumen https://github.com/nuwave/lighthouse/pull/1100
- Add test trait `\Nuwave\Lighthouse\Testing\UsesTestSchema` to enable using
  a dummy schema for testing custom Lighthouse extensions https://github.com/nuwave/lighthouse/pull/1171
- Simplify mocking resolvers that just return static data https://github.com/nuwave/lighthouse/pull/1177
- Add utility `\Nuwave\Lighthouse\ClientDirectives\ClientDirective` to correctly
  get the arguments passed through a client directive https://github.com/nuwave/lighthouse/pull/1184
- Add `streamGraphQL()` helper method to `\Nuwave\Lighthouse\Testing\MakesGraphQLRequests` for
  simple testing of streamed responses, such as `@defer` https://github.com/nuwave/lighthouse/pull/1184

### Fixed

- Fix eager-loading relations where the parent type is an `interface` or `union` and
  may correspond to multiple different models https://github.com/nuwave/lighthouse/pull/1035
- Fix renaming input fields that are nested within lists using `@rename` https://github.com/nuwave/lighthouse/pull/1166
- Fix handling of nested mutation operations that receive `null` https://github.com/nuwave/lighthouse/pull/1174
- Fix nested mutation `upsert` across two levels of BelongsTo relations https://github.com/nuwave/lighthouse/pull/1169
- Apply query filters using an `ArgBuilderDirective` such as `@eq` when the argument
  is nested deeply within the input https://github.com/nuwave/lighthouse/pull/1176
- Fix `\Nuwave\Lighthouse\Testing\MakesGraphQLRequestsLumen` test helper https://github.com/nuwave/lighthouse/pull/1186
- Handle multiple instances of client directives with `@defer` correctly https://github.com/nuwave/lighthouse/pull/1184

### Deprecated

- Deprecate `\Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider#setRootPath()`, this function
  is never called or used anywhere within Lighthouse. It will be removed from the interface.

## 4.8.1

### Fixed

- Avoid erasing the model information from the wrapping paginated results `type` when defining
  a paginated `@hasMany` field after a field with `@paginate` https://github.com/nuwave/lighthouse/pull/1149

## 4.8.0

### Added

- Compose complex input arguments through nested arg resolvers https://github.com/nuwave/lighthouse/pull/899
- Add `\Nuwave\Lighthouse\Support\Contracts\ArgResolver` directive interface https://github.com/nuwave/lighthouse/pull/899
- Allow existing mutation directives `@create`, `@update`, `@upsert` and `@delete` to function
  as nested arg resolvers https://github.com/nuwave/lighthouse/pull/899
- Validate at schema build time that the `apply` argument `@rules` is an array https://github.com/nuwave/lighthouse/pull/1092
- Add support in `@whereConditions` for IN, IS NULL and BETWEEN operators https://github.com/nuwave/lighthouse/pull/1099
- Add ability to define pivot data on nested mutations within `sync`, `syncWithoutDetach`
  and `connect` https://github.com/nuwave/lighthouse/pull/1110
- Allow restricting the columns for `@orderBy` to a given whitelist and generate
  an `enum` definition for it https://github.com/nuwave/lighthouse/pull/1118
- Allow passing variables in `->graphQL()` test helper https://github.com/nuwave/lighthouse/pull/1127
- Add missing schema descriptions to some inputs, types, and enums https://github.com/nuwave/lighthouse/pull/1131
- Add `@guard` directive to handle authentication https://github.com/nuwave/lighthouse/pull/1135
- Add `@whereHasConditions` directive to filter query results based on the existence
  of a relationship https://github.com/nuwave/lighthouse/pull/1140

### Changed

- Remove `\Nuwave\Lighthouse\Execution\MutationExecutor` in favour of modular
  nested arg resolvers https://github.com/nuwave/lighthouse/pull/899
- Register the operator enum for `@whereConditions` programmatically and allow
  overwriting it through a service provider https://github.com/nuwave/lighthouse/pull/1099
- Always automatically set the correct argument type when using `@whereConditions` or `@orderBy`
  directives https://github.com/nuwave/lighthouse/pull/1118
- Implement the `name()` function generically in the BaseDirective class https://github.com/nuwave/lighthouse/pull/1098
- Renamed the `@whereConstraints` directive to `@whereConditions` https://github.com/nuwave/lighthouse/pull/1140

### Fixed

- Enable chained rule provider directives (`ProvidesRules`) to merge the rules
  before validating https://github.com/nuwave/lighthouse/pull/1082
- Apply nested `OR` conditions in `@whereConditions` correctly https://github.com/nuwave/lighthouse/pull/1099
- Allow passing `null` or simply no `id` when using `@upsert` https://github.com/nuwave/lighthouse/pull/1114

### Deprecated

- The argument `field` within the `OrderByClause` used for `@orderBy` will be renamed to `column`
  in v5 https://github.com/nuwave/lighthouse/pull/1118
- Deprecated the `@middleware` directive, as it violates the boundary between HTTP and GraphQL
  request handling. Use `@guard` or other field middleware directives instead https://github.com/nuwave/lighthouse/pull/1135

### Removed

- Remove broken `NOT` conditional when using `@whereConditions` https://github.com/nuwave/lighthouse/pull/1125

## 4.7.2

### Fixed

- Enable multiple queries in a single request by clearing `BatchLoader` instances
  after executing each query https://github.com/nuwave/lighthouse/pull/1030
- Keep the query and pagination capabilities of relation directives when disabling batch loading https://github.com/nuwave/lighthouse/pull/1083

## 4.7.1

### Changed

- Add `INPUT_FIELD_DEFINITION` to allowed locations for the `@builder` directive https://github.com/nuwave/lighthouse/pull/1074

### Fixed

- Define `@enum` as a directive class so it shows up in `schema-directives.graphql`
  and can potentially be overwritten https://github.com/nuwave/lighthouse/pull/1078

## 4.7.0

### Added

- Add `syncWithoutDetaching` option for BelongsToMany and MorphToMany relationships https://github.com/nuwave/lighthouse/pull/1031
- Add `injectArgs` option to `@can` directive to pass along client defined
  arguments to the policy check https://github.com/nuwave/lighthouse/pull/1043
- Allow globally turning off relation batch loading through the
  config option `batchload_relations` https://github.com/nuwave/lighthouse/pull/1059
- Add `\Nuwave\Lighthouse\Execution\DataLoader\BatchLoader#loadMany()` function https://github.com/nuwave/lighthouse/pull/973
- Extend `@rename` directive to work with arguments and input fields https://github.com/nuwave/lighthouse/issues/521

### Changed

- Add ability to fetch soft deleted model within `@can` directive to validate permissions
  using `@softDeletes` directive. https://github.com/nuwave/lighthouse/pull/1042
- Improve the error message for missing field resolvers by offering a solution https://github.com/nuwave/lighthouse/pull/1045
- Throw `DefinitionException` when missing a type in the type registry https://github.com/nuwave/lighthouse/pull/1066
- Add `INPUT_FIELD_DEFINITION` to `orderBy` directive location https://github.com/nuwave/lighthouse/pull/1069

## 4.6.0

### Added

- Add `@scope` directive for adding a scope to the query builder https://github.com/nuwave/lighthouse/pull/998

### Changed

- Use detailed `$description` property when generating `enum` values from a `BenSampo\Enum\Enum` class  https://github.com/nuwave/lighthouse/pull/1027

### Fixed

- Handle arrays of namespaces in generator commands https://github.com/nuwave/lighthouse/pull/1033

## 4.5.3

### Fixed

- Handle `null` being passed to a nullable argument that is an input object type https://github.com/nuwave/lighthouse/pull/1021

## 4.5.2

### Fixed

- Fix conversion of client directives after the schema was cached https://github.com/nuwave/lighthouse/pull/1019

## 4.5.1

### Fixed

- Handle `null` being passed to a nullable argument that is a list of type https://github.com/nuwave/lighthouse/pull/1016

## 4.5.0

### Added

- Add `@upsert` directive and nested mutation operations to create or update a model
  regardless whether it exists https://github.com/nuwave/lighthouse/pull/1005

### Fixed

- Fix broken behaviour when using union types with schema caching https://github.com/nuwave/lighthouse/pull/1015 

## 4.4.2

### Added

- Validate the correctness of the `builder` given to `@paginate` at schema
  build time

### Fixed

- Do not require the type of a field matching a model class when using the
  `builder` argument of `@paginate` https://github.com/nuwave/lighthouse/pull/1011

## 4.4.1

### Fixed

- Fix regression in 4.4.0 that required matching the type returned from paginated relationship
  fields with the class name of the model https://github.com/nuwave/lighthouse/pull/1011

## 4.4.0

### Added

- Add `@count` directive for counting a relationship https://github.com/nuwave/lighthouse/pull/984
- Allow overwriting the name of Enum types created through `LaravelEnumType` https://github.com/nuwave/lighthouse/pull/968
- Resolve models through Relay's global identification using `@node` https://github.com/nuwave/lighthouse/pull/974
- Add experimental `@modelClass` directive to map types to models. It will be renamed
  to `@model` in v5 https://github.com/nuwave/lighthouse/pull/974

### Fixed

- Remove the extra new line from the returned value when using `@globalId(decode: "ID")` https://github.com/nuwave/lighthouse/pull/982
- Throw a syntax error instead of an exception when performing an
  empty request or a request with an empty query https://github.com/nuwave/lighthouse/pull/989
- Properly apply `@spread` when used within a nested input object https://github.com/nuwave/lighthouse/pull/992

### Changed

- Allow additional route configurations `prefix` and `domain` https://github.com/nuwave/lighthouse/pull/951
- Enable schema cache only when `APP_ENV` != 'local' https://github.com/nuwave/lighthouse/pull/957

### Fixed

- Fix default model detection when using other directives combination with `@paginate` https://github.com/nuwave/lighthouse/pull/974

### Deprecated

- Use the `RegisterDirectiveNamespaces` event instead of `DirectiveFactory#addResolved()` https://github.com/nuwave/lighthouse/pull/950
- Use `@node` instead of `@model` to resolve models through Relay's global identification https://github.com/nuwave/lighthouse/pull/974

## 4.3.0

### Added

- Add `@restore` and `@forceDelete` directives, similar to `@delete` https://github.com/nuwave/lighthouse/pull/941
- Add `@softDeletes` and `@trashed` directives to enable
  filtering soft deleted models https://github.com/nuwave/lighthouse/pull/937

### Fixed

- Prevent throwing in `lighthouse:ide-helper` when no custom directives are defined https://github.com/nuwave/lighthouse/pull/948

### Changed

- Validate requirements for argument definitions of `@delete`, `@forceDelete` and `@restore`
  during schema build time https://github.com/nuwave/lighthouse/pull/941

## 4.2.1

### Fixed

- Actually use the specified `edgeType` in Relay style connections https://github.com/nuwave/lighthouse/pull/939

## 4.2.0

### Added

- Add `@morphOne` directive for polymorphic one-to-one relationships https://github.com/nuwave/lighthouse/pull/944
- Add `@morphTo` directive for polymorphic one-to-one relationships https://github.com/nuwave/lighthouse/pull/921
- Add `@morphMany` directive for polymorphic one-to-many relationships https://github.com/nuwave/lighthouse/pull/944
- Support Laravel `^6.0` https://github.com/nuwave/lighthouse/pull/926
- Add command `lighthouse:ide-helper` for generating a definition file with all schema directives https://github.com/nuwave/lighthouse/pull/933

## 4.1.1

### Fixed

- Unbox laravel-enum inputs when using the builder directives https://github.com/nuwave/lighthouse/pull/927

## 4.1.0

### Added

- Add the `@whereJsonContains` directive to an input value as
  a [whereJsonContains filter
- Allow using callable classes with `__invoke` when referencing methods in directives
  and when looking for default resolvers or type resolvers https://github.com/nuwave/lighthouse/issues/882
- Allow to restrict column names to a well-defined list in `@whereContraints`
  and generate definitions for an `Enum` type and an `Input` type
  that are restricted to the defined columns https://github.com/nuwave/lighthouse/pull/916
- Add test helpers for introspection queries to `MakesGraphQLRequests` https://github.com/nuwave/lighthouse/pull/916

### Deprecated

- The default name of resolver and type resolver methods will be `__invoke` in v5 https://github.com/nuwave/lighthouse/issues/882

### Fixed

- Fixed the `ValidationDirective` not setting the mutation or query arguments to itself https://github.com/nuwave/lighthouse/pull/915

## 4.0.0

### Added

- Add the `@namespace` directive as a replacement for the removed `@group` directive https://github.com/nuwave/lighthouse/pull/768
- The `@defer` extension now supports deferring nested fields of mutations https://github.com/nuwave/lighthouse/pull/855
- Add a simple way to define complex validation directives by
  extending `\Nuwave\Lighthouse\Schema\Directives\ValidationDirective` https://github.com/nuwave/lighthouse/pull/846
- Extend the `@belongsToMany` directive to support pivot data on a custom Relay style Edge type https://github.com/nuwave/lighthouse/pull/871
- Implement `connect`, `disconnect` and `delete` operations for nested mutations upon MorphTo relationships https://github.com/nuwave/lighthouse/pull/879

### Fixed

- Avoid growing the memory extensively when doing complex AST manipulation https://github.com/nuwave/lighthouse/pull/768
- Make nested mutations work with subclassed relationship types https://github.com/nuwave/lighthouse/pull/825
- Allow empty arrays and other falsy values as input for nested mutation operations like "sync" https://github.com/nuwave/lighthouse/pull/830
- Use `Illuminate\Contracts\Config\Repository` instead of `Illuminate\Config\Repository` https://github.com/nuwave/lighthouse/issues/832
- Allow checking the abilities with `@can` when issuing mass updates on multiple models https://github.com/nuwave/lighthouse/pull/838
- Allow use of `private` in `@cache` directive even when the user is not authenticated https://github.com/nuwave/lighthouse/pull/843
- Fix Lumen route registration https://github.com/nuwave/lighthouse/pull/853
- Fix handling of `@include` directive, it is semantically opposite to `@skip`, when using it with `@defer` https://github.com/nuwave/lighthouse/pull/855
- Allow querying for null values using `@whereConstraints` https://github.com/nuwave/lighthouse/pull/872
- Fix issue when using the `@model` directive in a type that has a list field https://github.com/nuwave/lighthouse/pull/883
- Make the `@include` and `@skip` directives that are part of the GraphQL spec show up in introspection
  and fix handling of default values in custom client directives https://github.com/nuwave/lighthouse/pull/892

### Changed

- Bumped the requirement on `webonyx/graphql-php` to `^0.13.2` https://github.com/nuwave/lighthouse/pull/768
- Rename directive interfaces dealing with types from `Node*` to `Type*` https://github.com/nuwave/lighthouse/pull/768
- Change the signature of the AST manipulating directive interfaces:
  `TypeManipulator`, `FieldManipulator` and `ArgManipulator` https://github.com/nuwave/lighthouse/pull/768
- Change the API of the `DocumentAST` class to enable a more performant implementation https://github.com/nuwave/lighthouse/pull/768
- Enable the schema caching option `lighthouse.cache.enable` by default https://github.com/nuwave/lighthouse/pull/768 
- Lazily load types from the schema. Directives defined on parts of the schema that are not used within the current
  query are no longer run on every request https://github.com/nuwave/lighthouse/pull/768
- Simplify the default route configuration.
  Make sure to review your `config/lighthouse.php` and bring it up to date
  with the latest changes in the base configuration file https://github.com/nuwave/lighthouse/pull/820
- Move `SubscriptionExceptionHandler` into namespace `Nuwave\Lighthouse\Subscriptions\Contracts` https://github.com/nuwave/lighthouse/pull/819
- The pagination field argument that controls the amount of results
  now default tos `first` instead of `count`. The config `pagination_amount_argument`
  can be used to change the argument name https://github.com/nuwave/lighthouse/pull/852
- Rename `ArgValidationDirective` to `ProvidesRules` and drop `get` prefix from the methods within https://github.com/nuwave/lighthouse/pull/846
- Make the argument used for finding a model to check `@can` against configurable.
  The previous behaviour of implicitly using the `id` argument for finding a specific
  model to authorize against now no longer works. https://github.com/nuwave/lighthouse/pull/856
- Change the `Nuwave\Lighthouse\Schema\Types\LaravelEnumType` wrapper to map
  to Enum instances internally https://github.com/nuwave/lighthouse/pull/908

### Removed

- Remove `@group` directive in favour of `@middleware` and `@namespace` https://github.com/nuwave/lighthouse/pull/768
- Remove the `ArgFilterDirective` interface in favour of the `ArgBuilderDirective` interface https://github.com/nuwave/lighthouse/pull/821
- Remove the old style `@whereBetween` and `@whereNotBetween` directives https://github.com/nuwave/lighthouse/pull/821
- Use the `@spread` directive instead of the `flatten` argument of `@create`/`@update` https://github.com/nuwave/lighthouse/pull/822
- Remove `dispatch` aliases `fire` and `class` for dispatching through `@event` https://github.com/nuwave/lighthouse/pull/823
- Remove the `GraphQL` facade and the container alias `graphql` https://github.com/nuwave/lighthouse/pull/824
- Remove the alias `if` for specifying the `ability` that has to be met in `@can` https://github.com/nuwave/lighthouse/pull/838

### Deprecated

- The configuration option `pagination_amount_argument` will be removed in v5

## 3.7.0

### Added

- Add compatibility layer to allow `@middleware` to support Lumen https://github.com/nuwave/lighthouse/pull/786
- Add option `decode` to `@globaldId` to control the result of decoding https://github.com/nuwave/lighthouse/pull/796
- Add config option `cache.ttl` for customizing expiration time of schema cache https://github.com/nuwave/lighthouse/pull/801
- Extract test helpers into a reusable trait `\Nuwave\Lighthouse\Testing\MakesGraphQLRequests` https://github.com/nuwave/lighthouse/pull/802
- Support custom rule classes in `@rules` and `@rulesForArray` https://github.com/nuwave/lighthouse/pull/812

### Fixed

- Fix querying for falsy values through `@whereConstraints` https://github.com/nuwave/lighthouse/pull/800
- Use `Illuminate\Contracts\Events\Dispatcher` instead of concrete implementation
  in SubscriptionBroadcaster https://github.com/nuwave/lighthouse/pull/805

### Deprecated

- The `GraphQL` facade and the container alias `graphql` will be removed in v4 

## 3.6.1

### Fixed

- Use the spec-compliant default deprecation reason for `@deprecate` directive https://github.com/nuwave/lighthouse/pull/787

## 3.6.0

### Added

- Add `@whereConstraints` directive that offers flexible query capabilities to the client https://github.com/nuwave/lighthouse/pull/753
- Add convenience wrapper for registering Enum types based on [BenSampo/laravel-enum
  https://github.com/nuwave/lighthouse/pull/779

### Deprecated

- The `controller` config option will be removed in v4 https://github.com/nuwave/lighthouse/pull/781

## 3.5.3

### Fixed

- Respect the model's connection for database transaction during `@create` and `@update` https://github.com/nuwave/lighthouse/pull/777

## 3.5.2

### Fixed

- You can now omit an `input` argument from a query that uses
  the `@spread` directive without getting an error https://github.com/nuwave/lighthouse/pull/774

### Deprecated

- The class `SubscriptionExceptionHandler` will be moved to the namespace Nuwave\Lighthouse\Subscriptions\Contracts

## 3.5.1

### Fixed

- Throw error if pagination amount `<= 0` is requested https://github.com/nuwave/lighthouse/pull/765

## 3.5.0

### Changed

- Default the config to always set the `Accept: application/json` header https://github.com/nuwave/lighthouse/pull/743
- Declare a single named route which handles POST/GET instead of 2 separate routes https://github.com/nuwave/lighthouse/pull/738
- Apply the nested operations within a nested mutation in a consistent order
  that makes sense https://github.com/nuwave/lighthouse/pull/754

### Deprecated

- The pagination field argument that controls the amount of results
  will default to `first` instead of `count` in v4. The config `pagination_amount_argument`
  can be used to change the argument name now https://github.com/nuwave/lighthouse/pull/752

### Fixed

- Instantiate the `ErrorBuffer` directly, its dependencies
  can not be resolved through the container https://github.com/nuwave/lighthouse/pull/756
- Refresh GraphQLRequest singleton between multiple requests to prevent
  a common error in test execution https://github.com/nuwave/lighthouse/pull/761

## 3.4.0

### Added

- Allow rebinding a custom GlobalId resolver https://github.com/nuwave/lighthouse/pull/739

## 3.3.0

### Added

- Sync existing models in belongsToMany relations using nested mutations when creating https://github.com/nuwave/lighthouse/pull/707
- Add `@spread` directive to reshape nested input arguments https://github.com/nuwave/lighthouse/pull/680
- Add flexible `@builder` directive to quickly specify a single method to apply constraints
  to the query builder https://github.com/nuwave/lighthouse/pull/680
- Add `new_between_directives` config to use the new between directives now https://github.com/nuwave/lighthouse/pull/680

### Deprecated

- Use the `@spread` instead of the `flatten` argument of `@create`/`@update` https://github.com/nuwave/lighthouse/pull/680
- Prefer usage of the `ArgBuilderDirective` instead of the `ArgFilterDirective` https://github.com/nuwave/lighthouse/pull/680
- `@whereBetween` and `@whereNotBetween` will take a single input object
  instead of being spread across two args https://github.com/nuwave/lighthouse/pull/680

## 3.2.1

### Changed

- Flatten the namespace for the built-in directives https://github.com/nuwave/lighthouse/pull/700

## 3.2.0

### Added

- Sync and connect existing models in morphToMany relations using nested mutations https://github.com/nuwave/lighthouse/pull/707

## 3.1.0

### Added

- Adapt to the new Laravel way and add an alias `dispatch` for the `@event` directive https://github.com/nuwave/lighthouse/pull/719

### Deprecated

- Aliases `fire` and `class` for dispatching through `@event` https://github.com/nuwave/lighthouse/pull/719

## 3.0.0

### Added

- Support Subscriptions https://github.com/nuwave/lighthouse/pull/337
- Support `@defer` client directive https://github.com/nuwave/lighthouse/pull/422
- Define validation for list arguments themselves through `@rulesForArray` https://github.com/nuwave/lighthouse/pull/427
- The `@hasMany` and `@paginator` directives now support an additional argument `defaultCount`
  that sets a default value for the generated field argument `count` https://github.com/nuwave/lighthouse/pull/428
- Allow user to be guest when using the `@can` directive https://github.com/nuwave/lighthouse/pull/431
- Add shortcut to get NodeValue type definition fields https://github.com/nuwave/lighthouse/pull/432
- Use `@inject` with dot notation to set nested value https://github.com/nuwave/lighthouse/pull/511 
- Populate more relationship types through nested mutations https://github.com/nuwave/lighthouse/pull/514 https://github.com/nuwave/lighthouse/pull/549 
- Support the `@deprecated` directive https://github.com/nuwave/lighthouse/pull/522
- Allow defining default namespaces as an array https://github.com/nuwave/lighthouse/pull/525
- Add config & directive argument for `@paginate` to limit the maximum requested count https://github.com/nuwave/lighthouse/pull/569
- Add `guard` argument to `@auth` directive https://github.com/nuwave/lighthouse/pull/584
- Support Laravel 5.8 https://github.com/nuwave/lighthouse/pull/626
- Support File Uploads https://github.com/nuwave/lighthouse/pull/628
- Add lifecycle events to hook into the execution https://github.com/nuwave/lighthouse/pull/645
- Add `@orderBy` argument directive for client-side dynamic ordering https://github.com/nuwave/lighthouse/pull/659
- Enable passing in model instance to `@can` directive https://github.com/nuwave/lighthouse/pull/684
- Allow swapping out the default resolver https://github.com/nuwave/lighthouse/pull/690

### Changed

- Change the default schema location, model and GraphQL namespaces https://github.com/nuwave/lighthouse/pull/423
- Construction and methods of the Field|Node|Arg-Value objects https://github.com/nuwave/lighthouse/pull/425
- The methods called with `@method` now receive the same 4 resolver arguments that all
  other resolvers do https://github.com/nuwave/lighthouse/pull/486
- Handle mutating directives transactional by default https://github.com/nuwave/lighthouse/pull/512
- Nested mutations for BelongsTo require wrapping the ID in a
  `connect` argument https://github.com/nuwave/lighthouse/pull/514 https://github.com/nuwave/lighthouse/pull/549
- Make the error messages returned by `@can` more friendly https://github.com/nuwave/lighthouse/pull/515
- Bump requirements for `webonyx/graphql-php` to `^0.13` and PHP to `>= 7.1` https://github.com/nuwave/lighthouse/pull/517
- Replace `DirectiveRegistry` with `DirectiveFactory` to lazy load directives https://github.com/nuwave/lighthouse/pull/520
- Extensions must registered through ServiceProviders instead of the config file https://github.com/nuwave/lighthouse/pull/645
- Increase tracing precision when nanoseconds are available https://github.com/nuwave/lighthouse/pull/674

### Fixed

- Diverging paths of nested input objects can now have distinct validation rules https://github.com/nuwave/lighthouse/pull/427
- Distinguish between FieldDefinitions and InputObjectValues in AST handling https://github.com/nuwave/lighthouse/pull/425
- Set the date in the `Date` scalar to startOfDay, fixes equality checks https://github.com/nuwave/lighthouse/pull/452
- Use primary key defined in model to execute update https://github.com/nuwave/lighthouse/pull/469
- Consider batched queries when using BatchLoader https://github.com/nuwave/lighthouse/pull/508
- Refresh newly created models before returning them https://github.com/nuwave/lighthouse/pull/509
- Prevent name conflict between argument names and non-relation methods when executing nested mutations https://github.com/nuwave/lighthouse/pull/519
- Prevent crash when invalid JSON variables are given https://github.com/nuwave/lighthouse/pull/581
- Handle pagination with Laravel Scout correctly https://github.com/nuwave/lighthouse/pull/661
- Handle schema defined default values for enum types correctly https://github.com/nuwave/lighthouse/pull/689

### Removed

- Remove the previously broken `@validate` directive in favour of `@rules` https://github.com/nuwave/lighthouse/pull/427
- Remove broken user mutations from the default schema https://github.com/nuwave/lighthouse/pull/435
- Remove deprecated methods https://github.com/nuwave/lighthouse/pull/435
- Limit the `@field` directive to using the `resolver` argument https://github.com/nuwave/lighthouse/pull/435
- Remove the `@security` directive in favour of defining security options through the config https://github.com/nuwave/lighthouse/pull/435
- Rename the `resolver` argument of `@interface` and `@union` to `resolveType` https://github.com/nuwave/lighthouse/pull/435
- Remove deprecated Traits https://github.com/nuwave/lighthouse/pull/435

## Pre-v3

We just started maintaining a changelog starting from v3.

If someone wants to make one for previous versions, PR's are welcome.

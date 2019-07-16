# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/nuwave/lighthouse/compare/v3.7.0...master)

### Added

- Add the `@namespace` directive as a replacement for the removed `@group` directive https://github.com/nuwave/lighthouse/pull/768
- The `@defer` extension now supports deferring nested fields of mutations https://github.com/nuwave/lighthouse/pull/855
- Add a simple way to define complex validation directives by extending `\Nuwave\Lighthouse\Schema\Directives\ValidationDirective` https://github.com/nuwave/lighthouse/pull/846

### Fixed

- Avoid growing the memory extensively when doing complex AST manipulation https://github.com/nuwave/lighthouse/pull/768
- Make nested mutations work with subclassed relationship types https://github.com/nuwave/lighthouse/pull/825
- Allow empty arrays and other falsy values as input for nested mutation operations like "sync" https://github.com/nuwave/lighthouse/pull/830
- Use `Illuminate\Contracts\Config\Repository` instead of `Illuminate\Config\Repository` https://github.com/nuwave/lighthouse/issues/832
- Allow checking the abilities with `@can` when issuing mass updates on multiple models https://github.com/nuwave/lighthouse/pull/838
- Allow use of `private` in `@cache` directive even when the user is not authenticated https://github.com/nuwave/lighthouse/pull/843
- Fix Lumen route registration https://github.com/nuwave/lighthouse/pull/853
- Fix handling of `@include` directive, it is semantically opposite to `@skip`, when using it with `@defer` https://github.com/nuwave/lighthouse/pull/855

### Changed

- Bumped the requirement on `webonyx/graphql-php` to `^0.13.2` https://github.com/nuwave/lighthouse/pull/768
- Rename directive interfaces dealing with types from `Node*` to `Type*` https://github.com/nuwave/lighthouse/pull/768
- Change the signature of the AST manipulating directive interfaces: `TypeManipulator`, `FieldManipulator` and `ArgManipulator` https://github.com/nuwave/lighthouse/pull/768
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
- Make the argument used for finding a model to check @can against configurable.
  The previous behaviour of implicitely using the `id` argument for finding a specific
  model to authorize against now no longer works. https://github.com/nuwave/lighthouse/pull/856

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

## [3.7.0](https://github.com/nuwave/lighthouse/compare/v3.6.1...v3.7.0)

### Added

- Add compatibility layer to allow `@middleware` to support Lumen https://github.com/nuwave/lighthouse/pull/786
- Add option `decode` to `@globaldId` to control the result of decoding https://github.com/nuwave/lighthouse/pull/796
- Add config option `cache.ttl` for customizing expiration time of schema cache https://github.com/nuwave/lighthouse/pull/801
- Extract test helpers into a reusable trait `\Nuwave\Lighthouse\Testing\MakesGraphQLRequests` https://github.com/nuwave/lighthouse/pull/802
- Support custom rule classes in `@rules` and `@rulesForArray` https://github.com/nuwave/lighthouse/pull/812

### Fixed

- Fix querying for falsy values through `@whereConstraints` https://github.com/nuwave/lighthouse/pull/800
- Use `Illuminate\Contracts\Events\Dispatcher` instead of concrete implementation in SubscriptionBroadcaster https://github.com/nuwave/lighthouse/pull/805

### Deprecated

- The `GraphQL` facade and the container alias `graphql` will be removed in v4 

## [3.6.1](https://github.com/nuwave/lighthouse/compare/v3.6.0...v3.6.1)

### Fixed

- Use the spec-compliant default deprecation reason for `@deprecate` directive https://github.com/nuwave/lighthouse/pull/787

## [3.6.0](https://github.com/nuwave/lighthouse/compare/v3.5.3...v3.6.0)

### Added

- Add `@whereConstraints` directive that offers flexible query capabilities to the client https://github.com/nuwave/lighthouse/pull/753
- Add convenience wrapper for registering Enum types based on [BenSampo/laravel-enum](https://github.com/BenSampo/laravel-enum)
  https://github.com/nuwave/lighthouse/pull/779

### Deprecated

- The `controller` config option will be removed in v4 https://github.com/nuwave/lighthouse/pull/781

## [3.5.3](https://github.com/nuwave/lighthouse/compare/v3.5.2...v3.5.3)

### Fixed

- Respect the model's connection for database transaction during `@create` and `@update` https://github.com/nuwave/lighthouse/pull/777

## [3.5.2](https://github.com/nuwave/lighthouse/compare/v3.5.1...v3.5.2)

### Fixed

- You can now omit an `input` argument from a query that uses
  the `@spread` directive without getting an error https://github.com/nuwave/lighthouse/pull/774

### Deprecated

- The class `SubscriptionExceptionHandler` will be moved to the namespace Nuwave\Lighthouse\Subscriptions\Contracts

## [3.5.1](https://github.com/nuwave/lighthouse/compare/v3.5.0...v3.5.1)

### Fixed

- Throw error if pagination amount `<= 0` is requested https://github.com/nuwave/lighthouse/pull/765

## [3.5.0](https://github.com/nuwave/lighthouse/compare/v3.4.0...v3.5.0)

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

## [3.4.0](https://github.com/nuwave/lighthouse/compare/v3.3.0...v3.4.0) - 2019-04-18

### Added

- Allow rebinding a custom GlobalId resolver https://github.com/nuwave/lighthouse/pull/739

## [3.3.0](https://github.com/nuwave/lighthouse/compare/v3.2.1...v3.3.0) - 2019-04-15

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

## [3.2.1](https://github.com/nuwave/lighthouse/compare/v3.2.0...v3.2.1) - 2019-04-12

### Changed

- Flatten the namespace for the built-in directives https://github.com/nuwave/lighthouse/pull/700

## [3.2.0](https://github.com/nuwave/lighthouse/compare/v3.1.0...v3.2.0) - 2019-04-10

### Added

- Sync and connect existing models in morphToMany relations using nested mutations https://github.com/nuwave/lighthouse/pull/707

## [3.1.0](https://github.com/nuwave/lighthouse/compare/v3.0.0...v3.1.0) - 2019-04-07

### Added

- Adapt to the new Laravel way and add an alias `dispatch` for the `@event` directive https://github.com/nuwave/lighthouse/pull/719

### Deprecated

- Aliases `fire` and `class` for dispatching through `@event` https://github.com/nuwave/lighthouse/pull/719

## [3.0.0](https://github.com/nuwave/lighthouse/compare/v2.6.4...v3.0.0) - 2019-04-03

### Added

- Support Subscriptions https://github.com/nuwave/lighthouse/pull/337
- Support `@defer` client directive https://github.com/nuwave/lighthouse/pull/422
- Define validation for list arguments themselves through `@rulesForArray` https://github.com/nuwave/lighthouse/pull/427
- The `@hasMany` and `@paginator` directives now support an additional argument `defaultCount` that sets a default value for the generated field argument `count` https://github.com/nuwave/lighthouse/pull/428
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
- Nested mutations for BelongsTo require wrapping the ID in a `connect` argument https://github.com/nuwave/lighthouse/pull/514 https://github.com/nuwave/lighthouse/pull/549
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

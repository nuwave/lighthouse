# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/nuwave/lighthouse/compare/v3.6.1...master)

### Added

- Add compatibility layer to allow `@middleware` to support Lumen https://github.com/nuwave/lighthouse/pull/786
- Add option `decode` to `@globaldId` to control the result of decoding https://github.com/nuwave/lighthouse/pull/796

### Fixed

- Fix querying for falsy values through `@whereConstraints` https://github.com/nuwave/lighthouse/pull/800

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

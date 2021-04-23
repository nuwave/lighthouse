# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

You can find and compare releases at the [GitHub release page](https://github.com/nuwave/lighthouse/releases).

## Unreleased

## 5.6.1

### Fixed

- Fix overly eager validation of repeatable directive usage by requiring `webonyx/graphql-php:^14.6.2` https://github.com/nuwave/lighthouse/pull/1824
- Fix conversion of `repeatable` directive nodes into executable definitions https://github.com/nuwave/lighthouse/pull/1824

## 5.6.0

### Added

- Support for Apollo Federation https://github.com/nuwave/lighthouse/pull/1728

## 5.5.1

### Fixed

- Add placeholder type `_` to `schema-directives.graphql` https://github.com/nuwave/lighthouse/pull/1823

## 5.5.0

### Fixed

- Allow `@limit` on `FIELD_DEFINITION` to fix validation errors https://github.com/nuwave/lighthouse/pull/1821

### Added

- Add method `assertGraphQLErrorMessage()` to `TestResponse` mixin https://github.com/nuwave/lighthouse/pull/1819

## 5.4.0

### Added

- Add `GraphQLContext` to `StartExecution` event
- Add `connect` and `disconnect` operations in nested mutations for HasMany and MorphMany relations https://github.com/nuwave/lighthouse/pull/1730
- Add `ValidateSchema` event https://github.com/nuwave/lighthouse/pull/1764
- Add config option `subscriptions.exclude_empty` https://github.com/nuwave/lighthouse/pull/1799

### Changed

- Optimize `@defer` by avoiding parsing the request multiple times
- Move HTTP and Schema handling out of the GraphQL class https://github.com/nuwave/lighthouse/pull/1748
- Move subscription related classes into Subscription namespace https://github.com/nuwave/lighthouse/pull/1803
- Consolidate GlobalId namespace https://github.com/nuwave/lighthouse/pull/1804

### Fixed

- Apply error handling and debug settings consistently https://github.com/nuwave/lighthouse/pull/1749
- Fix typo `comparision` to `comparison` in generated input types for `@whereHas`
- Fix redis `mget` being called with an empty list of subscriber ids https://github.com/nuwave/lighthouse/pull/1759
- Fix `lighthouse:clear-cache` not clearing cache when a custom cache store is used https://github.com/nuwave/lighthouse/pull/1788
- Fix subscription storage in redis for predis users https://github.com/nuwave/lighthouse/pull/1814
- Prepend rule arguments that refer to other arguments with the full path https://github.com/nuwave/lighthouse/pull/1739

### Deprecated

- Deprecate the `globalId` argument on the `@upsert` directive https://github.com/nuwave/lighthouse/pull/1804

## 5.3.0

### Added

- Validate that `@with` and `@withCount` are not used on root fields https://github.com/nuwave/lighthouse/pull/1714
- Add events to cover the lifecycle of a GraphQL request: `EndExecution`, `EndRequest` https://github.com/nuwave/lighthouse/pull/1726
- Include the client given query, variables and operation name in the `StartExecution` event https://github.com/nuwave/lighthouse/pull/1726
- Apply `log` option from the `broadcasting` config to the Pusher subscription driver https://github.com/nuwave/lighthouse/pull/1733
- Support `pusher/pusher-php-server` version 5 https://github.com/nuwave/lighthouse/pull/1741

### Changed

- Prepend directives when transferring them from types to fields https://github.com/nuwave/lighthouse/pull/1734
- For `echo` driver-based subscriptions, the event name will be `lighthouse-subscription` https://github.com/nuwave/lighthouse/pull/1733
- Echo driver will always broadcast through the `private` channel https://github.com/nuwave/lighthouse/pull/1733

### Fixed

- Apply custom error handlers for syntax or request errors https://github.com/nuwave/lighthouse/pull/1726
- Define scalars instead of `Mixed` type in directive definitions https://github.com/nuwave/lighthouse/pull/1742
- Fix subscription extension version default value https://github.com/nuwave/lighthouse/pull/1744

## 5.2.0

### Added

- Allow using the `@builder` directive on fields https://github.com/nuwave/lighthouse/pull/1687
- Add dedicated `\Nuwave\Lighthouse\Scout\ScoutBuilderDirective` https://github.com/nuwave/lighthouse/pull/1691
- Allow `@eq` directive on fields https://github.com/nuwave/lighthouse/pull/1681
- Add `@throttle` directive to set field rate limit using Laravel rate limiting services https://github.com/nuwave/lighthouse/pull/1708
- Add subscriptions v2 https://github.com/nuwave/lighthouse/pull/1716

### Changed

- Clarify semantics of combining `@search` with other directives https://github.com/nuwave/lighthouse/pull/1691
- Move Scout related classes into `\Nuwave\Lighthouse\Scout` https://github.com/nuwave/lighthouse/pull/1698
- `BaseDirective` loads all arguments and caches them after the first `directiveHasArgument`/`directiveArgValue` call https://github.com/nuwave/lighthouse/pull/1707
- Use gate response in authorization errors of `@can` directive https://github.com/nuwave/lighthouse/pull/1715

### Fixed

- Fix nested `OR` conditions in `HAS` relations https://github.com/nuwave/lighthouse/pull/1713

### Deprecated

- Specify `@guard(with: "api")` should be changed to `@guard(with: ["api"])`https://github.com/nuwave/lighthouse/pull/1705

## 5.1.0

### Added

- Allow spec-compliant definition of the `messages` argument on `@rules` and `@rulesForArray` https://github.com/nuwave/lighthouse/pull/1662
- Validate correct usage of `@rules` and `@rulesForArray` https://github.com/nuwave/lighthouse/pull/1662
- Allow eager-loading multiple relations on a single field using `@with` https://github.com/nuwave/lighthouse/pull/1528
- Add `\Nuwave\Lighthouse\Execution\DataLoader\BatchLoaderRegistry` to instantiate arbitrary batch loaders https://github.com/nuwave/lighthouse/pull/1528
- Add `@limit` directive to allow clients to specify the maximum number of results to return https://github.com/nuwave/lighthouse/pull/1674
- Predefine default field ordering by using `@orderBy` on fields https://github.com/nuwave/lighthouse/pull/1678
- Add `@like` directive to use a client given value to add a `LIKE` conditional to a database query https://github.com/nuwave/lighthouse/issues/1644

### Changed

- Improve batch loading performance https://github.com/nuwave/lighthouse/pull/1528
- Require `webonyx/graphql-php` version `^14.5`

### Deprecated

- Deprecate the `globalId` argument on the `@delete`, `@forceDelete` and `@restore` directives https://github.com/nuwave/lighthouse/pull/1660
- Deprecate passing the `messages` argument on `@rules` and `@rulesForArray` as a map with arbitrary keys https://github.com/nuwave/lighthouse/pull/1662
- Deprecate `\Nuwave\Lighthouse\Execution\DataLoader\BatchLoader` in favour of `\Nuwave\Lighthouse\Execution\DataLoader\BatchLoaderRegistry` https://github.com/nuwave/lighthouse/pull/1528

### Fixed

- Remove non-functional `globalId` argument definition from `@update` https://github.com/nuwave/lighthouse/pull/1660
- Resolve field middleware directives in lexical order https://github.com/nuwave/lighthouse/pull/1666
- Ensure `Carbon\Carbon` is cast to `Illuminate\Support\Carbon` in date scalars https://github.com/nuwave/lighthouse/pull/1672
- Fix Laravel 5.6 compatibility for `@withCount` and paginated relationship directives https://github.com/nuwave/lighthouse/pull/1528
- Fix issue where argument names where used instead of variable names in subscription queries https://github.com/nuwave/lighthouse/pull/1683
- Fix issue with TTL breaking subscriptions https://github.com/nuwave/lighthouse/pull/1685

## 5.0.2

### Fixed

- Make `@node` force load the type if it has not been loaded https://github.com/nuwave/lighthouse/pull/1659

## 5.0.1

### Fixed

- Make `@model` not extend `@node` accidentally

### Deprecated

- Deprecate `\Nuwave\Lighthouse\Subscriptions\Subscriber::setRoot()` in favour of property access

## 5.0.0

### Added

- Apply validation rules to input types by providing a validator class https://github.com/nuwave/lighthouse/pull/1185
- Include schema directives when running `php artisan lighthouse:validate-schema` https://github.com/nuwave/lighthouse/pull/1494
- Add ability to query for the existence of relations in where conditions https://github.com/nuwave/lighthouse/pull/1412
- Handle content types `application/graphql` and `application/x-www-form-urlencoded` properly https://github.com/nuwave/lighthouse/pull/1424
- Mark directives that can be used more than once per location as `repeatable` https://github.com/nuwave/lighthouse/pull/1529
- Allow configuring global field middleware directives in `config/lighthouse.php` https://github.com/nuwave/lighthouse/pull/1533
- Add custom attributes to validations https://github.com/nuwave/lighthouse/pull/1628
- Add new directive interface `FieldBuilderDirective` https://github.com/nuwave/lighthouse/pull/1636
- Add `@whereAuth` directive for filtering a field based on authenticated user https://github.com/nuwave/lighthouse/pull/1636
- Use the `@trim` directive on fields to sanitize all input strings https://github.com/nuwave/lighthouse/pull/1641
- Add Laravel Echo compatible subscription broadcaster https://github.com/nuwave/lighthouse/pull/1370
- Allow auxiliary types in directive definitions https://github.com/nuwave/lighthouse/pull/1649

### Changed

- Use `Illuminate\Support\Carbon` instead of `Carbon\Carbon`
- `\Nuwave\Lighthouse\Exceptions\ValidationException` no longer extends `\Illuminate\Validation\ValidationException` https://github.com/nuwave/lighthouse/pull/1185
- Move validation related classes into namespace `Nuwave\Lighthouse\Validation` https://github.com/nuwave/lighthouse/pull/1185
- Run `ArgDirectives` in distinct phases: Sanitize, Validate, Transform https://github.com/nuwave/lighthouse/pull/1185
- The directive interfaces `ArgBuilderDirective`, `ArgTransformerDirective` and `ArgDirectiveForArray`
  extend `Directive` instead of `ArgDirective` https://github.com/nuwave/lighthouse/pull/1185
- Change the autogeneration of the `OrderByClause` input for `@orderBy`, the
  argument `field` is now always called `column` https://github.com/nuwave/lighthouse/pull/1337
- Names for autogenerated types are now prepended with the name of the fields parent type
  This affects `@orderBy`, `@whereConditions` and `@whereHasConditions` https://github.com/nuwave/lighthouse/pull/1337
- `__invoke` is now the only supported method name for convention based
  field resolver classes  https://github.com/nuwave/lighthouse/pull/1422
- Change `\Nuwave\Lighthouse\Support\Contracts\Directive` to require all directives to have an SDL
  definition by implementing `public static definition(): string` https://github.com/nuwave/lighthouse/pull/1386
- Combine `Nuwave\Lighthouse\Schema\Factories\DirectiveNamespacer` and `Nuwave\Lighthouse\Schema\Factories\DirectiveFactory` into
  `\Lighthouse\Schema\DirectiveLocator` https://github.com/nuwave/lighthouse/pull/1494
- Require `haydenpierce/class-finder` as a built-in dependency https://github.com/nuwave/lighthouse/pull/1494
- Add method `defaultHasOperator` to `\Nuwave\Lighthouse\WhereConditions\Operator` https://github.com/nuwave/lighthouse/pull/1412
- Change default configuration options in `lighthouse.php`:
  - `'guard' => 'api'`
  - `'forceFill' => true`
- Use `laragraph/utils` for parsing HTTP requests https://github.com/nuwave/lighthouse/pull/1424
- Replace the subscription broadcast queued event handler with a queued job to allow the queue name to be specified https://github.com/nuwave/lighthouse/pull/1507
- Make `@method` call the underlying method with the arguments as ordered parameters instead
  of the full resolver arguments https://github.com/nuwave/lighthouse/pull/1509
- Change `ErrorHandler` method `handle()` to non-static `__invoke()` and allow discarding
  errors by returning `null`
- Allow subscriptions without named operations, base channels on the field name
- Set `lighthouse.debug` config through env `LIGHTHOUSE_DEBUG` https://github.com/nuwave/lighthouse/pull/1592
- Test helper `multipartGraphQL` now accepts arrays instead of JSON strings https://github.com/nuwave/lighthouse/pull/1615/
- Use `DateTime::ATOM` for DateTimeTZ ISO 8601 compatibility https://github.com/nuwave/lighthouse/pull/1622
- Split `ProvidesRules` interface into `ArgumentValidation` and `ArgumentSetValidation` https://github.com/nuwave/lighthouse/pull/1628
- Update to PHP 8 compatible mll-lab/graphql-php-scalars 4 https://github.com/nuwave/lighthouse/pull/1639
- Add `TrimDirective` to the default `field_middleware` config in `lighthouse.php` https://github.com/nuwave/lighthouse/pull/1641
- Field keys in validation errors now match the client given input, ignoring transformations such as `@spread` https://github.com/nuwave/lighthouse/issues/1631

### Removed

- Remove support for PHP 7.1, Laravel 5.5 and PHPUnit 6 https://github.com/nuwave/lighthouse/pull/1192
- Remove `TestResponse::jsonGet()` helper, use `->json()` instead https://github.com/nuwave/lighthouse/pull/1192/files
- Remove `\Nuwave\Lighthouse\Execution\GraphQLValidator` as validation now uses Laravel's native validator https://github.com/nuwave/lighthouse/pull/1185
- Remove interfaces `HasArgumentPath` and `HasErrorBuffer` and the parts of `FieldFactory` that calls them https://github.com/nuwave/lighthouse/pull/1185
- Remove the `ValidationDirective` abstract class in favour of validator classes https://github.com/nuwave/lighthouse/pull/1185
- Remove configuration option `lighthouse.orderBy`, always uses `column` now https://github.com/nuwave/lighthouse/pull/1337
- Remove `\Nuwave\Lighthouse\Support\Contracts\DefinedDirective` interface, moving its
  functionality to `\Nuwave\Lighthouse\Support\Contracts\Directive` https://github.com/nuwave/lighthouse/pull/1386
- Remove fallback for `lighthouse.cache.ttl` setting https://github.com/nuwave/lighthouse/pull/1423
- Remove `Nuwave\Lighthouse\Schema\AST\PartialParser` in favor of `GraphQL\Language\Parser` https://github.com/nuwave/lighthouse/pull/1457
- Remove `Nuwave\Lighthouse\Execution\GraphQLRequest` singleton https://github.com/nuwave/lighthouse/pull/1424
- Remove `@bcrypt` in favor of `@hash` https://github.com/nuwave/lighthouse/pull/1200
- Remove the `@middleware` directive, as it violates the boundary between HTTP and GraphQL
  request handling. Use `@guard` or other field middleware directives instead https://github.com/nuwave/lighthouse/pull/1135
- Remove configuration option `pagination_amount_argument`, it is always `first` now

### Fixed

- Prefix complex conditions with table name to avoid ambiguous SQL https://github.com/nuwave/lighthouse/pull/1530
- Merge type interfaces when extending type https://github.com/nuwave/lighthouse/pull/1635

### Deprecated

- Deprecate values for the `type` argument of `@paginate` that are not `PAGINATOR` or `CONNECTION`

## 4.18.0

### Added

- Add `@morphToMany` directive https://github.com/nuwave/lighthouse/pull/1604

## 4.17.0

### Added

- Support Laravel 8 https://github.com/nuwave/lighthouse/pull/1549 and https://github.com/nuwave/lighthouse/pull/1578

## 4.16.3

### Fixed

- Fix the type hint in `GraphQLContext::user()`

## 4.16.2

### Fixed

- Provide the definition for `@nest`

## 4.16.1

### Fixed

- Ensure the `@with` directive works properly with polymorphic relations https://github.com/nuwave/lighthouse/pull/1517

## 4.16.0

### Added

- Add artisan command `lighthouse:cache` to compile GraphQL AST https://github.com/nuwave/lighthouse/pull/1451
- Add middleware `\Nuwave\Lighthouse\Support\Http\Middleware\LogGraphQLQueries` that logs every incoming
  GraphQL query https://github.com/nuwave/lighthouse/pull/1454
- Allow custom query validation rules selection by rebinding the interface
  `\Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules` https://github.com/nuwave/lighthouse/pull/1487
- Add `DateTimeUtc` scalar https://github.com/nuwave/lighthouse/pull/1320

### Changed

- Publish config file with tag `lighthouse-config` and default schema with tag `lighthouse-schema`
  instead of the previously used tags `config` and `schema` https://github.com/nuwave/lighthouse/issues/1489
- Throw partial errors when failing to delete, forceDelete or restore a model https://github.com/nuwave/lighthouse/pull/1420
- Add `\Nuwave\Lighthouse\Execution\ErrorPool` to allow collection of partial errors https://github.com/nuwave/lighthouse/pull/1420

### Fixed

- Ensure the `@count` directive works properly with polymorphic relations https://github.com/nuwave/lighthouse/pull/1466
- Take route prefix into account in `graphQLEnpointUrl()` test helper https://github.com/nuwave/lighthouse/pull/1439

### Deprecated

- Deprecate `\Nuwave\Lighthouse\Execution\ErrorBuffer` in favor of `\Nuwave\Lighthouse\Execution\ErrorPool` https://github.com/nuwave/lighthouse/pull/1420

## 4.15.0

### Added

- Add `@withCount` directive to eager load relationship counts on field access https://github.com/nuwave/lighthouse/pull/1390
- Extend `lighthouse:directive` artisan command to allow choosing interfaces https://github.com/nuwave/lighthouse/pull/1251
- Add `lighthouse.cache.store` configuration option to set the cache store to use for schema caching https://github.com/nuwave/lighthouse/pull/1446

### Changed

- Eager load relationship count in `@count` directive https://github.com/nuwave/lighthouse/pull/1390
- Simplify the default field resolver classes generated by the artisan commands `lighthouse:query` and `lighthouse:mutation`,
  add option `--full` to include the seldom needed resolver arguments `$context` and `$resolveInfo`

### Fixed

- Restore application of global scopes in nested relation queries when batching pagination https://github.com/nuwave/lighthouse/pull/1447
- Avoid unnecessarily reloading models with count in nested relation queries https://github.com/nuwave/lighthouse/pull/1447

## 4.14.1

### Fixed

- Safeguard deletion of `programmatic-types.graphql` in `artisan lighthouse:ide-helper`

## 4.14.0

### Added

- Write definitions for programmatically registered types to `programmatic-types.graphql`
  when running the `lighthouse:ide-helper` artisan command https://github.com/nuwave/lighthouse/pull/1371

### Fixed

- Fix the error message when using multiple exclusive directives on a single node https://github.com/nuwave/lighthouse/pull/1387
- Allow passing additional headers to `multipartGraphQL` Lumen test helper too https://github.com/nuwave/lighthouse/pull/1395
- Rectify that `@orderBy`, `@whereConditions` and `@whereHasConditions` only work on field arguments https://github.com/nuwave/lighthouse/pull/1402
- Make mass assignment behavior configurable through `force_fill` option in `lighthouse.php` https://github.com/nuwave/lighthouse/pull/1405

### Deprecated

- `\Nuwave\Lighthouse\Support\Contracts\DefinedDirective::definition()` will be moved to `\Nuwave\Lighthouse\Support\Contracts\Directive`
  and replace its `name()` method. This requires all directives to have an SDL definition.

## 4.13.1

### Fixed

- Pull primary key from arguments in `@update` before force filling them into the Model https://github.com/nuwave/lighthouse/pull/1377

## 4.13.0

### Added

- Allow passing additional headers to `multipartGraphQL` test helper https://github.com/nuwave/lighthouse/pull/1342
- Add empty root types automatically when extending them https://github.com/nuwave/lighthouse/pull/1347
- Configure a default `guard` for all authentication functionality https://github.com/nuwave/lighthouse/pull/1343
- Configure the default amount of items in paginated lists with `pagination.default_count` https://github.com/nuwave/lighthouse/pull/1352
- Add new methods `has()`, `overwrite()` and `registerNew()` to `TypeRegistry` to control if types should
  be overwritten when registering duplicates https://github.com/nuwave/lighthouse/pull/1361

### Changed

- Improve validation error when extending a type that is not defined https://github.com/nuwave/lighthouse/pull/1347
- Use `forceFill()` when mutating models https://github.com/nuwave/lighthouse/pull/1348
- Namespace pagination related configuration in `lighthouse.php` https://github.com/nuwave/lighthouse/pull/1352
- Fix publishing the config when using Lumen https://github.com/nuwave/lighthouse/pull/1355

### Deprecated

- The setting `paginate_max_count` will change to `pagination.max_count` https://github.com/nuwave/lighthouse/pull/1352
- The `registerNew()` method of `TypeRegistry` will be removed in favor of `register()`, which will change
  its behavior to throw when registering duplicates https://github.com/nuwave/lighthouse/pull/1361

## 4.12.4

### Fixed

- Fix nesting OR within AND condition when using `@whereConditions` https://github.com/nuwave/lighthouse/pull/1341

## 4.12.3

### Changed

- Throw an exception if the return type declaration class for a relation does not exist https://github.com/nuwave/lighthouse/pull/1338

## 4.12.2

### Fixed

- Fix converting lists of lists into ArgumentSet https://github.com/nuwave/lighthouse/pull/1335

### Changed

- Make test request helper PHPDocs more accurate for Laravel 7 https://github.com/nuwave/lighthouse/pull/1336

## 4.12.1

### Fixed

- Fix creating multiple nested BelongsTo relationships on the same level when previous records
  with matching attributes exist https://github.com/nuwave/lighthouse/pull/1321

## 4.12.0

### Added

- Add flag `--json` to `print-schema` to output JSON instead of GraphQL SDL https://github.com/nuwave/lighthouse/pull/1268
- Add TTL option for subscriptions storage https://github.com/nuwave/lighthouse/pull/1284
- Provide assertion helpers through `TestResponseMixin` https://github.com/nuwave/lighthouse/pull/1308
- Add scalar `DateTimeTz` https://github.com/nuwave/lighthouse/pull/1311
- Publish `_lighthouse_ide_helper.php` with `php artisan lighthouse:ide-helper`

### Fixed

- Fix nested mutations with multiple `belongsTo` relations at the same level https://github.com/nuwave/lighthouse/pull/1285
- Avoid race condition that occurs when using `Cache::has()` https://github.com/nuwave/lighthouse/pull/1290
- Replace usage of `resolve()` helper with Lumen-compatible `app()` https://github.com/nuwave/lighthouse/pull/1305
- Fix using `@create` and `@update` on nested input object fields that accept an array of input types  
  https://github.com/nuwave/lighthouse/pull/1316

### Changed

- Remove subscriber reference from topic when deleted https://github.com/nuwave/lighthouse/pull/1288
- Improve subscription context serializer https://github.com/nuwave/lighthouse/pull/1283
- Allow replacing the `SubscriptionRegistry` implementation using the container https://github.com/nuwave/lighthouse/pull/1286
- Report errors that are not client-safe through Laravel's `ExceptionHandler` https://github.com/nuwave/lighthouse/pull/1303
- Log in subscribers when broadcasting a subscription update, so that calls to `auth()->user()` return
  the authenticated user instead of `null` https://github.com/nuwave/lighthouse/pull/1306
- Replace the subscription broadcast queued event handler with a queued job to allow the queue name to be specified https://github.com/nuwave/lighthouse/pull/1301

## 4.11.0

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

- Remove `@bcrypt` in favor of `@hash` https://github.com/nuwave/lighthouse/pull/1200
- `@method` will call the underlying method with the arguments as ordered parameters instead
  of the full resolver arguments https://github.com/nuwave/lighthouse/pull/1208

## 4.9.0

### Added

- Add optional `columnsEnum` argument to the `@whereConditions`, `@whereHasConditions`
  and `@orderBy` directives https://github.com/nuwave/lighthouse/pull/1150
- Exclude or include trashed models in `@can` when `@forceDelete` or `@restore` are used,
  the client does not have to filter explicitly https://github.com/nuwave/lighthouse/pull/1157
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
- Add ability to define pivot data on nested mutations within `sync`, `syncWithoutDetaching`
  and `connect` https://github.com/nuwave/lighthouse/pull/1110
- Allow restricting the columns for `@orderBy` to a given whitelist and generate
  an `enum` definition for it https://github.com/nuwave/lighthouse/pull/1118
- Allow passing variables in `->graphQL()` test helper https://github.com/nuwave/lighthouse/pull/1127
- Add missing schema descriptions to some inputs, types, and enums https://github.com/nuwave/lighthouse/pull/1131
- Add `@guard` directive to handle authentication https://github.com/nuwave/lighthouse/pull/1135
- Add `@whereHasConditions` directive to filter query results based on the existence
  of a relationship https://github.com/nuwave/lighthouse/pull/1140

### Changed

- Remove `\Nuwave\Lighthouse\Execution\MutationExecutor` in favor of modular
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

- Use detailed `$description` property when generating `enum` values from a `BenSampo\Enum\Enum` class https://github.com/nuwave/lighthouse/pull/1027

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

- Remove `@group` directive in favor of `@middleware` and `@namespace` https://github.com/nuwave/lighthouse/pull/768
- Remove the `ArgFilterDirective` interface in favor of the `ArgBuilderDirective` interface https://github.com/nuwave/lighthouse/pull/821
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

- Remove the previously broken `@validate` directive in favor of `@rules` https://github.com/nuwave/lighthouse/pull/427
- Remove broken user mutations from the default schema https://github.com/nuwave/lighthouse/pull/435
- Remove deprecated methods https://github.com/nuwave/lighthouse/pull/435
- Limit the `@field` directive to using the `resolver` argument https://github.com/nuwave/lighthouse/pull/435
- Remove the `@security` directive in favor of defining security options through the config https://github.com/nuwave/lighthouse/pull/435
- Rename the `resolver` argument of `@interface` and `@union` to `resolveType` https://github.com/nuwave/lighthouse/pull/435
- Remove deprecated Traits https://github.com/nuwave/lighthouse/pull/435
- Remove `\Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions::subscriberByRequest()`

## Pre-v3

We just started maintaining a changelog starting from v3.

If someone wants to make one for previous versions, PR's are welcome.

# Upgrade guide

This document provides guidance for upgrading between major versions of Lighthouse.

## General tips

The configuration options often change between major versions.
Compare your `lighthouse.php` against the latest [default configuration](src/lighthouse.php).

## v6 to v7

### Leverage automatic test trait setup

Methods you need to explicitly call to set up test traits were removed in favor of automatically set up test traits.
Keep in mind they only work when your test class extends `Illuminate\Foundation\Testing\TestCase`.

- Just remove calls to `Nuwave\Lighthouse\Testing\RefreshesSchemaCache::bootRefreshesSchemaCache()`.
- Replace calls to `Nuwave\Lighthouse\Testing\MakesGraphQLRequests::setUpSubscriptionEnvironment()` with ` use Nuwave\Lighthouse\Testing\TestsSubscriptions`.

### `EnsureXHR` is enabled in the default configuration

The middleware `Nuwave\Lighthouse\Http\Middleware\EnsureXHR` is enabled in the default configuration.
It will prevent the following type of HTTP requests:
- `GET` requests
- `POST` requests that can be created using HTML forms

### `@can` directive is replaced with `@can*` directives

The `@can` directive was removed in favor of more specialized directives:
- with `find` field set: `@canFind`
- with `query` field set: `@canQuery`
- with `root` field set: `@canRoot`
- with `resolved` field set: `@canResolved`
- if none of the above are set: `@canModel`

```diff
type Mutation {
-   createPost(input: PostInput! @spread): Post! @can(ability: "create") @create
+   createPost(input: PostInput! @spread): Post! @canModel(ability: "create") @create
-   updatePost(input: PostInput! @spread): Post! @can(find: "input.id", ability: "edit") @update
+   updatePost(input: PostInput! @spread): Post! @canFind(find: "input.id", ability: "edit") @update
-   deletePosts(ids: [ID!]! @whereKey): [Post!]! @can(query: true, ability: "delete") @delete
+   deletePosts(ids: [ID!]! @whereKey): [Post!]! @canQuery(ability: "delete") @delete
}

type Query {
-   posts: [Post!]! @can(resolved: true, ability: "view") @paginate
+   posts: [Post!]! @canResolved(ability: "view") @paginate
}

type Post {
-   sensitiveInformation: String @can(root: true, ability: "admin")
+   sensitiveInformation: String @canRoot(ability: "admin")
}
```

## v5 to v6

### `messages` on `@rules` and `@rulesForArray`

Lighthouse previously allowed passing a map with arbitrary keys as the `messages`
argument of `@rules` and `@rulesForArray`. Such a construct is impossible to define
within the directive definition and leads to static validation errors.

```diff
@rules(
    apply: ["max:280"],
-   messages: {
-       max: "Tweets have a limit of 280 characters"
-   }
+   messages: [
+       {
+           rule: "max"
+           message: "Tweets have a limit of 280 characters"
+       }
+   ]
)
```

### Use filters in `@delete`, `@forceDelete` and `@restore`

Whereas previously, those directives enforced the usage of a single argument and assumed that
to be the ID or list of IDs of the models to modify, they now leverage argument filter directives.
This brings them in line with other directives such as `@find` and `@all`.

You will need to explicitly add `@whereKey` to the argument that contained the ID or IDs.

```diff
type Mutation {
-   deleteUser(id: ID!): User! @delete
+   deleteUser(id: ID! @whereKey): User! @delete
-   restoreUsers(userIDs: [ID!]!): [User!]! @restore
+   restoreUsers(userIDs: [ID!]! @whereKey): [User!]! @restore
}
```

### Use `@globalId` over `@delete(globalId: true)`

The `@delete`, `@forceDelete`, `@restore` and `@upsert` directives no longer offer the
`globalId` argument. Use `@globalId` on the argument instead.

```diff
type Mutation {
-   deleteUser(id: ID!): User! @delete(globalId: true)
+   deleteUser(id: ID! @globalId @whereKey): User! @delete
}
```

### Specify `@guard(with: "api")` as `@guard(with: ["api"])`

Due to Lighthouse's ongoing effort to provide static schema validation,
the `with` argument of `@guard` must now be provided as a list of strings.

```diff
type Mutation {
-   somethingSensitive: Boolean @guard(with: "api")
+   somethingSensitive: Boolean @guard(with: ["api"])
}
```

### Use subscriptions response format version 2

The previous version 1 contained a redundant key `channels` and is no longer supported.

```diff
{
  "data": {...},
  "extensions": {
    "lighthouse_subscriptions": {
-     "version": 1,
+     "version": 2,
      "channel": "channel-name"
-     "channels": {
-       "subscriptionName": "channel-name"
-     },
    }
  }
}
```

It is recommended to switch to version 2 before upgrading Lighthouse to give clients
a smooth transition period.

### Nullability of pagination results

Generated result types of paginated lists are now always marked as non-nullable.
The setting `non_null_pagination_results` was removed and now always behaves as if it were `true`.

This is generally more convenient for clients, but will
cause validation errors to bubble further up in the result.

### Nullability of pagination `first`

Previously, the pagination argument `first` was either marked as non-nullable,
or non-nullable with a default value.

Now, it will always be marked as non-nullable, regardless if it has a default or not.
This prevents clients from passing an invalid explicit `null`.

### Complexity calculation

Prior to `v6`, overwriting the default query complexity calculation on paginated fields
required the usage of `@complexity` without any arguments. Now, `@paginate` performs that
calculation by default - with the additional change that it also includes the cost of the
field itself, adding a value of `1` to represent the complexity more accurately.

Using `@complexity` without the `resolver` argument is now no longer supported.

### Passing of `BenSampo\Enum\Enum` instances to `ArgBuilderDirective::handleBuilder()`

Prior to `v6`, Lighthouse would extract the internal `$value` from instances of
`BenSampo\Enum\Enum` before passing it to `ArgBuilderDirective::handleBuilder()`
if the setting `unbox_bensampo_enum_enum_instances` was `true`.

This is generally unnecessary, because Laravel automagically calls the Enum's `__toString()`
method when using it in a query. This might affect users who use an `ArgBuilderDirective`
that delegates to a method that relies on an internal value being passed.

```graphql
type Query {
    withEnum(byType: AOrB @scope): WithEnum @find
}
```

```php
// WithEnum.php
public function scopeByType(Builder $builder, int $aOrB): Builder
{
    return $builder->where('type', $aOrB);
}
```

In the future, Lighthouse will pass the actual Enum instance along. You can opt in to
the new behaviour before upgrading by setting `unbox_bensampo_enum_enum_instances` to `false`. 

```php
public function scopeByType(Builder $builder, AOrB $aOrB): Builder
```

### Return resolver from `FieldResolver::resolveField()`

Instead of calling `FieldValue::setResolver()`, directly return the resolver function.

```diff
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class MyDirective extends BaseDirective implements FieldResolver
{
-   public function resolveField(FieldValue $fieldValue): FieldValue
-   {
-       $fieldValue->setResolver(function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): int {
-           return 42;
-       });
-       return $fieldValue;
+   public function resolveField(FieldValue $fieldValue): callable
+   {
+       return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): int {
+           return 42;
+       };
    }
}
```

### Simplify wrapping resolvers in `FieldMiddleware` directives

Wrapping resolvers is very common in `FieldMiddleware` directives and is now simplified.

```diff
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class MyDirective extends BaseDirective implements FieldMiddleware
{
-   public function handleField(FieldValue $fieldValue, \Closure $next): FieldValue
-   {
-       $previousResolver = $fieldValue->getResolver();
-       $fieldValue->setResolver(function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
-           return $previousResolver($root, $args, $context, $resolveInfo);
-       });
-       return $next($fieldValue);
+   public function handleField(FieldValue $fieldValue): void
+   {
+       $fieldValue->wrapResolver(fn (callable $previousResolver) => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
+           return $previousResolver($root, $args, $context, $resolveInfo);
+       });
    }
}
```

### Adopt `FieldBuilderDirective::handleFieldBuilder()` signature

Lighthouse now passes the typical 4 resolver arguments to `FieldBuilderDirective::handleFieldBuilder()`.
Custom directives the implement `FieldBuilderDirective` now have to accept those extra arguments.

```diff
+ use Nuwave\Lighthouse\Execution\ResolveInfo
+ use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class MyDirective extends BaseDirective implements FieldBuilderDirective
{
-    public function handleFieldBuilder(object $builder): object;
+    public function handleFieldBuilder(object $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): object;
}
```

### Use `ResolveInfo::enhanceBuilder()`

`ArgumentSet::enhanceBuilder()` was removed.
You must now call `ResolveInfo::enhanceBuilder()` and pass the resolver arguments.

```diff
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

// Some resolver function or directive middleware
function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
-   $resolveInfo->argumentSet->enhanceBuilder($builder, $scopes, $directiveFilter);
+   $resolveInfo->enhanceBuilder($builder, $scopes, $root, $args, $context, $resolveInfo, $directiveFilter);
```

### Replace `Nuwave\Lighthouse\GraphQL::executeQuery()` usage

Use `executeQueryString()` for executing a string query or `executeParsedQuery()` for 
executing an already parsed `DocumentNode` instance.

### Removed error extension field `category`

See https://github.com/webonyx/graphql-php/blob/master/UPGRADE.md#breaking-removed-error-extension-field-category.

You can [leverage `GraphQL\Error\ProvidesExtensions`](https://lighthouse-php.com/master/digging-deeper/error-handling.html#additional-error-information)
to restore `category` in your custom exceptions. Additionally, you may [implement a custom error handler](https://lighthouse-php.com/master/digging-deeper/error-handling.html#registering-error-handlers)
that wraps well-known third-party exceptions with your own exception that adds an appropriate `category`.

### Use native interface for errors with extensions

Use `GraphQL\Error\ProvidesExtensions::getExtensions()` over `Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions::extensionsContent()`
to return extra information from exceptions:

```diff
use Exception;
-use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;
+use GraphQL\Error\ClientAware;
+use GraphQL\Error\ProvidesExtensions;

-class CustomException extends Exception implements RendersErrorsExtensions
+class CustomException extends Exception implements ClientAware, ProvidesExtensions
{
-   public function extensionsContent(): array
+   public function getExtensions(): array
```

### Use `RefreshesSchemaCache` over `ClearsSchemaCache`

The `ClearsSchemaCache` testing trait was prone to race conditions when running tests in parallel.

```diff
-use Nuwave\Lighthouse\Testing\ClearsSchemaCache;
+use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
-   use ClearsSchemaCache;
+   use RefreshesSchemaCache;

    protected function setUp(): void
    {
        parent::setUp();
-       $this->bootClearsSchemaCache();
+       $this->bootRefreshesSchemaCache();
     }
}
```

### Schema caching v1 removal

Schema caching now uses v2 only. That means, the schema cache will be
written to a php file that OPCache will pick up instead of being written
to the configured cache driver. This significantly reduces memory usage.

If you had previously depended on the presence of the schema in your
cache, then you will need to change your code.

### Register `ScoutServiceProvider` if you use `@search`

If you use the `@search` directive in your schema,
you will now need to register the service provider `Nuwave\Lighthouse\Scout\ScoutServiceProvider`,
it is no longer registered by default.
See [registering providers in Laravel](https://laravel.com/docs/providers#registering-providers).

### Update `lighthouse.guard` configuration

The `lighthouse.guard` configuration key was renamed to `lighthouse.guards` and expects an array.

```diff
- 'guard' => 'api',
+ 'guards' => ['api'],
```

If `lighthouse.guards` configuration is missing,
the default Laravel authentication guard will be used (`auth.defaults.guard`).

### Update `@auth` and `@whereAuth` directives

The `guard` argument of `@auth` and `@whereAuth` directives has been renamed to `guards` and now expects a list instead of a single string.

```diff
- @auth(guard: "api")
+ @auth(guards: ["api"])
- @whereAuth(guard: "api")
+ @whereAuth(guards: ["api"])
```

## v4 to v5

### Update PHP, Laravel and PHPUnit

The following versions are now the minimal required versions:

- PHP 7.2
- Laravel 5.6
- PHPUnit 7

### Final schema may change

Parts of the final schema are automatically generated by Lighthouse. Clients that depend on
specific fields or type names may have to adapt. The recommended process for finding breaking
changes is:

1. Print your schema before upgrading: `php artisan lighthouse:print-schema > old.graphql`
1. Upgrade, then re-print your schema: `php artisan lighthouse:print-schema > new.graphql`
1. Use [graphql-inspector](https://github.com/kamilkisiela/graphql-inspector) to compare your
   changes: `graphql-inspector diff old.graphql new.graphql`

### Rename `resolve` to `__invoke`

Field resolver classes now only support the method name `__invoke`, using
the name `resolve` no longer works.

```diff
namespace App\GraphQL\Queries;

class SomeField
{
-   public function resolve(...
+   public function __invoke(...
```

### Replace `@middleware` with `@guard` and specialized FieldMiddleware

The `@middleware` directive has been removed, as it violates the boundary between HTTP and GraphQL request handling.
Laravel middleware acts upon the HTTP request as a whole, whereas field middleware must only apply to a part of it. 

If you used `@middleware` for authentication, replace it with [@guard](docs/master/api-reference/directives.md#guard):

```diff
type Query {
-   profile: User! @middleware(checks: ["auth"])
+   profile: User! @guard
}
```

Note that [@guard](docs/master/api-reference/directives.md#guard) does not log in users.
To ensure the user is logged in, add the `AttemptAuthenticate` middleware to your `lighthouse.php`
middleware config, see the [default config](src/lighthouse.php) for an example.

If you used `@middleware` for authorization, replace it with [@can](docs/master/api-reference/directives.md#can).

Other functionality can be replaced by a custom [`FieldMiddleware`](docs/master/custom-directives/field-directives.md#fieldmiddleware)
directive. Just like Laravel Middleware, it can wrap around individual field resolvers.

### Directives must have an SDL definition

The interface `\Nuwave\Lighthouse\Support\Contracts\Directive` now has the same functionality
as the removed `\Nuwave\Lighthouse\Support\Contracts\DefinedDirective`. If you previously
implemented `DefinedDirective`, remove it from your directives:

```diff
-use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

-class TrimDirective extends BaseDirective implements ArgTransformerDirective, DefinedDirective
+class TrimDirective extends BaseDirective implements ArgTransformerDirective
```

Instead of just providing the name of the directive, all directives must now return an SDL
definition that formally describes them.

```diff
-    public function name()
-    {
-        return 'trim';
-    }

+    /**
+     * Formal directive specification in schema definition language (SDL).
+     *
+     * @return string
+     */
+    public static function definition(): string
+    {
+        return /** @lang GraphQL */ <<<'GRAPHQL'
+"""
+A description of what this directive does.
+"""
+directive @trim(
+    """
+    Directives can have arguments to parameterize them.
+    """
+    someArg: String
+) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
+GRAPHQL;
+    }
```

### `@orderBy` argument renamed to `column`

The argument to specify the column to order by when using `@orderBy` was renamed
to `column` to match the `@whereConditions` directive.

Client queries will have to be changed like this:

```diff
{
    posts (
        orderBy: [
            {
-               field: POSTED_AT
+               column: POSTED_AT
                order: ASC
            }
        ]
    ) {
        title
    }
}
```

If you absolutely cannot break your clients, you can re-implement `@orderBy` in your
project - it is a relatively simple `ArgManipulator` directive.

### `@modelClass` and `@model` changed

The `@model` directive was repurposed to take the place of `@modelClass`. As a replacement
for the current functionality of `@model`, the new `@node` directive was added,
see https://github.com/nuwave/lighthouse/pull/974 for details.

You can adapt to this change in two refactoring steps that must be done in order:

1. Rename all usages of `@model` to `@node`, e.g.:

   ```diff
   -type User @model {
   +type User @node {
       id: ID! @globalId
   }
   ```

2. Rename all usages of `@modelClass` to `@model`, e.g.

   ```diff
   -type PaginatedPost @modelClass(class: "\\App\\Post") {
   +type PaginatedPost @model(class: "\\App\\Post") {
       id: ID!
   }
   ```

### Replace `@bcrypt` with `@hash`

The new `@hash` directive is also used for password hashing, but respects the
configuration settings of your Laravel project.

```diff
type Mutation {
    createUser(
        name: String!
-       password: String! @bcrypt
+       password: String! @hash
    ): User!
}
```

### `@method` passes down just ordered arguments

Instead of passing down the usual resolver arguments, the `@method` directive will
now pass just the arguments given to a field. This behaviour could previously be
enabled through the `passOrdered` option, which is now removed.

```graphql
type User {
  purchasedItemsCount(year: Int!, includeReturns: Boolean): Int @method
}
```

The method will have to change like this:

```diff
-public function purchasedItemsCount(mixed $root, array $args)
+public function purchasedItemsCount(int $year, ?bool $includeReturns)
```

### Implement `ArgDirective` or `ArgDirectiveForArray` explicitly

This affects custom directives that implemented one of the following interfaces:

- `\Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray`
- `\Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective`
- `\Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective`

Whereas those interfaces previously extended `\Nuwave\Lighthouse\Support\Contracts\ArgDirective`, you now
have to choose if you want them to apply to entire lists of arguments, elements within that list, or both.
Change them as follows to make them behave like in v4:

```diff
+use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

-class MyCustomArgDirective extends BaseDirective implements ArgTransformerDirective, DefinedDirective
+class MyCustomArgDirective extends BaseDirective implements ArgTransformerDirective, DefinedDirective, ArgDirective
```

### `ArgDirective` run in distinct phases

The application of directives that implement the `ArgDirective` interface is
split into three distinct phases:

- Sanitize: Clean the input, e.g. trim whitespace.
  Directives can hook into this phase by implementing `ArgSanitizerDirective`.
- Validate: Ensure the input conforms to the expectations, e.g. check a valid email is given
- Transform: Change the input before processing it further, e.g. hashing passwords.
  Directives can hook into this phase by implementing `ArgTransformerDirective`

### Replace custom validation directives with validator classes

The `ValidationDirective` abstract class was removed in favour of validator classes.
They represent a more lightweight way and flexible way to reuse complex validation rules,
not only on fields but also on input objects.

To convert an existing custom validation directive to a validator class, change it as follows:

```diff
<?php

-namespace App\GraphQL\Directives;
+namespace App\GraphQL\Validators;

use Illuminate\Validation\Rule;
-use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;
+use Nuwave\Lighthouse\Validation\Validator;

-class UpdateUserValidationDirective extends ValidationDirective
+class UpdateUserValidator extends Validator
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'id' => ['required'],
-            'name' => ['sometimes', Rule::unique('users', 'name')->ignore($this->args['id'], 'id')],
+            'name' => ['sometimes', Rule::unique('users', 'name')->ignore($this->arg('id'), 'id')],
        ];
    }
}
```

Instead of directly using this class as a directive, place the `@validator` directive on your field.

```graphql
type Mutation {
- updateUser(id: ID, name: String): User @update @updateUserValidation
+ updateUser(id: ID, name: String): User @update @validator
}
```

### `Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent` is no longer fired

The event is no longer fired, and the event class was removed. Lighthouse now uses a queued job instead.

If you manually fired the event, replace it by queuing a `Nuwave\Lighthouse\Subscriptions\BroadcastSubscriptionJob`
or a call to `Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions::queueBroadcast()`.

In case you depend on an event being fired whenever a subscription is queued, you can bind your
own implementation of `Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions`.

### `TypeRegistry` does not register duplicates by default

Calling `register()` on the `\Nuwave\Lighthouse\Schema\TypeRegistry` now throws when passing
a type that was already registered, as this most likely is an error.

If you want to previous behaviour of overwriting existing types, use `overwrite()` instead.

```diff
$typeRegistry = app(\Nuwave\Lighthouse\Schema\TypeRegistry::class);
-$typeRegistry->register($someType);
+$typeRegistry->overwrite($someType);
```

### Mass assignment protection is disabled by default

Since GraphQL constrains allowed inputs by design, mass assignment protection is not needed.
By default, Lighthouse will use `forceFill()` when populating a model with arguments in mutation directives.
This allows you to use mass assignment protection for other cases where it is actually useful.

If you need to revert to the old behavior of using `fill()`, you can change your `lighthouse.php`:

```diff
-   'force_fill' => true,
+   'force_fill' => false,
```

### Replace `ErrorBuffer` with `ErrorPool`

Collecting partial errors is now done through the singleton `\Nuwave\Lighthouse\Execution\ErrorPool`
instead of `\Nuwave\Lighthouse\Execution\ErrorBuffer`:

```php
try {
    // Something that might fail but still allows for a partial result
} catch (\Throwable $error) {
    $errorPool = app(\Nuwave\Lighthouse\Execution\ErrorPool::class);
    $errorPool->record($error);
}

return $result;
```

### Use native `TestResponse::json()`

The `TestResponse::jsonGet()` mixin was removed in favor of the `->json()` method,
natively supported by Laravel starting from version 5.6.

```diff
$response = $this->graphQL(...);
-$response->jsonGet(...);
+$response->json(...);
```

### Use `GraphQL\Language\Parser` instead of `Nuwave\Lighthouse\Schema\AST\PartialParser`

The native parser from [webonyx/graphql-php](https://github.com/webonyx/graphql-php) now supports partial parsing.

```diff
-use Nuwave\Lighthouse\Schema\AST\PartialParser;
+use GraphQL\Language\Parser;
```

Most methods work the same:

```diff
-PartialParser::directive(/** @lang GraphQL */ '@deferrable')
+Parser::constDirective(/** @lang GraphQL */ '@deferrable')
```

A few are different:

```diff
-PartialParser::listType("[$restrictedOrderByName!]");
+Parser::typeReference("[$restrictedOrderByName!]");

-PartialParser::inputValueDefinitions([$foo, $bar]);
+Parser::inputValueDefinition($foo);
+Parser::inputValueDefinition($bar);
```

### Add method `defaultHasOperator` to `\Nuwave\Lighthouse\WhereConditions\Operator`

Since the addition of the `HAS` input in `whereCondition` mechanics,
there has to be a default operator for the `HAS` input.

If you implement your own custom operator, implement `defaultHasOperator`.
For example, this is the implementation of the default `\Nuwave\Lighthouse\WhereConditions\SQLOperator`:

```php
public function defaultHasOperator(): string
{
    return 'GTE';
}
```

### Change `ErrorHandler` method `handle()`

If you implemented your own error handler, change it like this:

```diff
use Nuwave\Lighthouse\Execution\ErrorHandler;

class ExtensionErrorHandler implements ErrorHandler
{
-   public static function handle(Error $error, Closure $next): array
+   public function __invoke(?Error $error, Closure $next): ?array
    {
        ...
    }
}
```

You can now discard errors by returning `null` from the handler.

### Upgrade to `mll-lab/graphql-php-scalars` v4

If you use complex where condition directives, such as `@whereConditions`,
upgrade `mll-lab/graphql-php-scalars` to v4:

    composer require mll-lab/graphql-php-scalars:^4

### Subscriptions version 1 removal 

Subscriptions only use version 2 now. That means, the extensions content
will not contain the `channels` and `version` key anymore.

```diff
{
  "data": {...},
  "extensions": {
    "lighthouse_subscriptions": {
-      "version": 1,
      "channel": "channel-name",
-      "channels": {
-        "subscriptionName": "channel-name"
-      }
    }
  }
}
```

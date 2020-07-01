# Upgrade guide

This document provides guidance for upgrading between major versions of Lighthouse.

## General tips

The configuration options often change between major versions.
Compare your `lighthouse.php` against the latest [default configuration](src/lighthouse.php).

## v4 to v5

### Replace @middleware with @guard and specialized FieldMiddleware

The `@middleware` directive has been removed, as it violates the boundary between HTTP and GraphQL
request handling.

Authentication is one of most common use cases for `@middleware`. You can now use
the [@guard](docs/master/api-reference/directives.md#guard) directive on selected fields.

```diff
type Query {
-   profile: User! @middlware(checks: ["auth"])
+   profile: User! @guard
}
```

Note that [@guard](docs/master/api-reference/directives.md#guard) does not log in users.
To ensure the user is logged in, add the `AttemptAuthenticate` middleware to your `lighthouse.php`
middleware config, see the [default config](src/lighthouse.php) for an example.

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
+        return /** @lang GraphQL */ <<<'SDL'
+"""
+A description of what this directive does.
+"""
+directive @trim(
+    """
+    Directives can have arguments to parameterize them.
+    """
+    someArg: String
+) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
+SDL;
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
-public function purchasedItemsCount($root, array $args)
+public function purchasedItemsCount(int $year, ?bool $includeReturns)
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

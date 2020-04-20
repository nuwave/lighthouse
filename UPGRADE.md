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
the [`@guard`](docs/master/api-reference/directives.md#guard) directive on selected fields.

```diff
type Query {
-   profile: User! @middlware(checks: ["auth"])
+   profile: User! @guard
}
```

Note that [`@guard`](docs/master/api-reference/directives.md#guard) does not log in users.
To ensure the user is logged in, add the `AttemptAuthenticate` middleware to your `lighthouse.php`
middleware config, see the [default config](src/lighthouse.php) for an example.

Other functionality can be replaced by a custom [`FieldMiddleware`](docs/master/custom-directives/field-directives.md#fieldmiddleware)
directive. Just like Laravel Middleware, it can wrap around individual field resolvers.

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
     * @return mixed[]
     */
    public function rules(): array
    {
        return [
            'id' => ['required'],
            'name' => ['sometimes', Rule::unique('users', 'name')->ignore($this->args['id'], 'id')],
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

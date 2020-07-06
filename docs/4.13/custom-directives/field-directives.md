# Field Directives

Field directives can be applied to any [FieldDefinition](https://graphql.github.io/graphql-spec/June2018/#FieldDefinition)

## FieldResolver

Perhaps the most important directive interface, a [`\Nuwave\Lighthouse\Support\Contracts\FieldResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/FieldResolver.php)
let's you add a resolver for a field through a directive.

It can be a great way to reuse resolver logic within a schema.

## FieldMiddleware

A [`\Nuwave\Lighthouse\Support\Contracts\FieldMiddleware`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/FieldMiddleware.php) directive allows you
to wrap around the field resolver, just like [Laravel Middleware](https://laravel.com/docs/middleware).

You may use it both to handle incoming values before reaching the final resolver
as well as the outgoing result of resolving the field.

## FieldManipulator

An [`\Nuwave\Lighthouse\Support\Contracts\FieldManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/FieldManipulator.php)
directive can be used to manipulate the schema AST.

## ValidationDirective

This directive type is implemented as an abstract class rather then a pure interface and allows
you to define complex validation rules for a field with ease.

[Read more about it in the Validation section](../security/validation.md#validate-fields).

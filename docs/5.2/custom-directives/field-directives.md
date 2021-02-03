# Field Directives

Field directives can be applied to any [FieldDefinition](https://graphql.github.io/graphql-spec/June2018/#FieldDefinition)

## FieldResolver

Perhaps the most important directive interface, a [`\Nuwave\Lighthouse\Support\Contracts\FieldResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/FieldResolver.php)
lets you add a resolver for a field through a directive.

It can be a great way to reuse resolver logic within a schema.

## FieldMiddleware

A [`\Nuwave\Lighthouse\Support\Contracts\FieldMiddleware`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/FieldMiddleware.php) directive allows you
to wrap around the field resolver, just like [Laravel Middleware](https://laravel.com/docs/middleware).

You may use it to handle incoming values before reaching the final resolver
as well as the outgoing result of resolving the field.

```php
<?php

namespace App\GraphQL\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ExampleDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
directive @example on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        // If you have any work to do that does not require the resolver arguments, do it here.
        // This code is executed only once per field, whereas the resolver can be called often.

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            // Do something before the resolver, e.g. validate $args, check authentication

            // Call the actual resolver
            $result = $resolver($root, $args, $context, $resolveInfo);

            // Do something with the result, e.g. transform some fields

            return $result;
        });

        // Keep the chain of adding field middleware going by calling the next handler.
        // Calling this before or after ->setResolver() allows you to control the
        // order in which middleware is wrapped around the field.
        return $next($fieldValue);
    }
}
```

## FieldBuilderDirective

A [`\Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective`](https://github.com/nuwave/lighthouse/blob/master/src/Support/Contracts/FieldBuilderDirective.php)
directive allows modifying the database query that Lighthouse creates for a field.

The following directives use the defined filter for resolving the query:

- [@whereAuth](../api-reference/directives.md#whereauth)

## FieldManipulator

A [`\Nuwave\Lighthouse\Support\Contracts\FieldManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/FieldManipulator.php)
directive can be used to manipulate the schema AST.

## ValidationDirective

This directive type is implemented as an abstract class rather then a pure interface and allows
you to define complex validation rules for a field with ease.

[Read more about it in the Validation section](../security/validation.md#validate-fields).

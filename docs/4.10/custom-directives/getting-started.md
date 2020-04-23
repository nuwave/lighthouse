# Implementing Your Own Directives

As you grow your GraphQL schema, you may find the need for more specialized functionality.
Learn how you can abstract logic in a composable and reusable manner by using custom directives.

## Naming Conventions

Directives are implemented as PHP classes, each directive available
in the schema corresponds to a single class.

Directive names themselves are typically defined in **camelCase**.
The class name of a directive must follow the following pattern:

    <DirectiveName>Directive

Let's implement a simple `@upperCase` directive as a part of this introduction.
We will put it in a class called `UpperCaseDirective` and extend the
abstract class `\Nuwave\Lighthouse\Schema\Directives\BaseDirective`.

```php
<?php

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class UpperCaseDirective extends BaseDirective {}
```

## Directive Interfaces

At this point, the directive does not do anything.
Depending on what your directive should do, you can pick one or more of the provided
directive interfaces to add functionality. They serve as the point of contact to Lighthouse.

In this case, our directive needs to run after the actual resolver.
Just like [Laravel Middleware](https://laravel.com/docs/middleware),
we can wrap around it by using the `FieldMiddleware` directive.

```php
<?php

namespace App\GraphQL\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UpperCaseDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Wrap around the final field resolver.
     *
     * @param \Nuwave\Lighthouse\Schema\Values\FieldValue $fieldValue
     * @param \Closure $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        // Retrieve the existing resolver function
        /** @var Closure $previousResolver */
        $previousResolver = $fieldValue->getResolver();

        // Wrap around the resolver
        $wrappedResolver = function ($root, array $args, GraphQLContext $context, ResolveInfo $info) use ($previousResolver): string {
            // Call the resolver, passing along the resolver arguments
            /** @var string $result */
            $result = $previousResolver($root, $args, $context, $info);

            return strtoupper($result);
        };

        // Place the wrapped resolver back upon the FieldValue
        // It is not resolved right now - we just prepare it
        $fieldValue->setResolver($wrappedResolver);

        // Keep the middleware chain going
        return $next($fieldValue);
    }
}
```

## Register Directives

Now that we defined and implemented the directive, how can Lighthouse find it?

When Lighthouse encounters a directive within the schema, it starts looking for a matching class
in the following order:

1. User-defined namespaces as configured in `config/lighthouse.php`, defaults to `App\GraphQL\Directives`
1. The [RegisterDirectiveNamespaces](../api-reference/events.md#registerdirectivenamespaces) event is dispatched
   to gather namespaces defined by plugins, extensions or other listeners
1. Lighthouse's built-in directive namespace

This means that our directive is already registered, just by matter of defining it in the default namespace,
and will take precedence over potential other directives with the same name.

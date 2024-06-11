# Implementing Your Own Directives

As you grow your GraphQL schema, you may find the need for more specialized functionality.
You can abstract logic in a composable and reusable manner by using custom directives.

## Naming Convention

Directives are implemented as PHP classes.
Each directive available in the schema corresponds to a single class.

Directive names themselves are typically defined in **camelCase**.
The class name of a directive must follow the following pattern:

    <DirectiveName>Directive

Let's implement a simple `@upperCase` directive as a part of this introduction.
Use the artisan generator command to create it:

    php artisan lighthouse:directive --argument upperCase

That will create a class called `UpperCaseDirective` that extends the
abstract class `\Nuwave\Lighthouse\Schema\Directives\BaseDirective`.

```php
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

final class UpperCaseDirective extends BaseDirective
{
    /**
     * Formal directive specification in schema definition language (SDL).
     *
     * @return string
     */
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
A description of what this directive does.
"""
directive @upperCase(
    """
    Directives can have arguments to parameterize them.
    """
    someArg: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }
}
```

## Directive Interfaces

At this point, the directive does not do anything.
Depending on what your directive should do, you can pick one or more of the provided directive interfaces to add functionality.
They serve as the point of contact to Lighthouse.

In this case, our directive needs to run after the actual resolver.
Just like [Laravel Middleware](https://laravel.com/docs/middleware), we can wrap around it by using the `FieldMiddleware` directive.

```php
namespace App\GraphQL\Directives;

use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class UpperCaseDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string {}

    /**
     * Wrap around the final field resolver.
     */
    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(fn (callable $resolver) => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $info) use ($resolver): string {
            // Call the resolver, passing along the resolver arguments
            $result = $resolver($root, $args, $context, $info);
            assert(is_string($result));

            return strtoupper($result);
        });
    }
}
```

Given there are a lot of use cases for custom directives, the documentation does not provide examples for most interfaces.
It is advised to look at the Lighthouse source code to find directives that implement certain interfaces to learn more.

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

<?php

namespace Tests\Utils\Directives;

use Closure;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class CustomFieldMiddlewareDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
directive @customFieldMiddleware on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        return $next(
            $fieldValue->setResolver(
                /**
                 * @param  array<string, mixed>  $args
                 * @return array<string, mixed>
                 */
                static function ($root, array $args): array {
                    return $args;
                }
            )
        );
    }
}

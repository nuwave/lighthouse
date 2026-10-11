<?php declare(strict_types=1);

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

final class CustomFieldMiddlewareDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        directive @customFieldMiddleware on FIELD_DEFINITION
        GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(static fn (): callable => static fn (mixed $root, array $args): array => $args);
    }
}

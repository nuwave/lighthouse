<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class NamespacedDirective extends BaseDirective implements FieldResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Provides a no-op field resolver that allows nesting of queries and mutations.
Useful to implement [namespacing by separation of concerns](https://www.apollographql.com/docs/technotes/TN0012-namespacing-by-separation-of-concern).
"""
directive @namespaced on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        return static fn (): bool => true;
    }
}

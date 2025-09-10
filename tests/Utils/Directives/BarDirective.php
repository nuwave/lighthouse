<?php declare(strict_types=1);

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

final class BarDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        """
        Maximum foo.
        """
        directive @bar on FIELD_DEFINITION
        GRAPHQL;
    }
}

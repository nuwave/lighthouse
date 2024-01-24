<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments\Fixtures;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

final class Nested extends BaseDirective implements ArgResolver
{
    public function __invoke(mixed $root, $args): void {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ 'directive @nested on FIELD_DEFINITION';
    }
}

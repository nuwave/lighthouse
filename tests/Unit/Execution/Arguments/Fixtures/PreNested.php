<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments\Fixtures;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\PreSaveArgResolver;

final class PreNested extends BaseDirective implements PreSaveArgResolver
{
    public function __invoke(mixed $root, $args): void {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        directive @preNested on INPUT_FIELD_DEFINITION
        GRAPHQL;
    }
}

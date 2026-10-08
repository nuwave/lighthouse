<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;

final class SaveAwareNested extends BaseDirective implements SaveAwareArgResolver
{
    public bool $wasCalled = false;

    public mixed $receivedRoot = null;

    public function __invoke(mixed $root, $args): void
    {
        $this->wasCalled = true;
        $this->receivedRoot = $root;
    }

    public function runBeforeSave(Model $model): bool
    {
        return true;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        directive @saveAwareNested on INPUT_FIELD_DEFINITION
        GRAPHQL;
    }
}

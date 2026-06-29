<?php declare(strict_types=1);

namespace Tests\Utils\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\PreSaveArgResolver;

final class UppercaseDirective extends BaseDirective implements PreSaveArgResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        directive @uppercase on INPUT_FIELD_DEFINITION
        GRAPHQL;
    }

    public function __invoke(mixed $root, $args): void
    {
        assert($root instanceof Model);
        $root->setAttribute($this->nodeName(), strtoupper($args));
    }
}

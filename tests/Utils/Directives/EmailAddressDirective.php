<?php declare(strict_types=1);

namespace Tests\Utils\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;

final class EmailAddressDirective extends BaseDirective implements SaveAwareArgResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        directive @emailAddress on INPUT_FIELD_DEFINITION
        GRAPHQL;
    }

    public function runBeforeSave(Model $model): bool
    {
        return true;
    }

    /**
     * @param  Model  $model
     * @param  ArgumentSet|null  $args
     */
    public function __invoke($model, $args): void
    {
        if ($args === null) {
            return;
        }

        $parts = $args->toArray();
        $model->setAttribute('email', "{$parts['local']}@{$parts['domain']}");
    }
}

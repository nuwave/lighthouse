<?php declare(strict_types=1);

namespace Tests\Utils\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;

final class GeocodeDirective extends BaseDirective implements SaveAwareArgResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        directive @geocode on INPUT_FIELD_DEFINITION
        GRAPHQL;
    }

    public function runBeforeSave(Model $model): bool
    {
        return true;
    }

    /**
     * @param  Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): void
    {
        $address = $args->toArray();
        $model->setAttribute('latitude', $address['lat']);
        $model->setAttribute('longitude', $address['lng']);
    }
}

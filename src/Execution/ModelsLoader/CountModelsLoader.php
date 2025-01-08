<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use GraphQL\Utils\Utils;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CountModelsLoader implements ModelsLoader
{
    public function __construct(
        protected string $relation,
        protected \Closure $decorateBuilder,
    ) {}

    public function load(EloquentCollection $parents): void
    {
        $parents->loadCount([$this->relation => $this->decorateBuilder]);
    }

    public function extract(Model $model): int
    {
        return self::extractCount($model, $this->relation);
    }

    public static function extractCount(Model $model, string $relationName): int
    {
        /**
         * This is the name that Eloquent gives to the attribute that contains the count.
         *
         * @see \Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withCount()
         */
        $countAttributeName = Str::snake("{$relationName}_count");

        $count = $model->getAttribute($countAttributeName);
        if (! is_numeric($count)) {
            $nonNumericCount = Utils::printSafe($count);
            throw new \Exception("Expected numeric count, got: {$nonNumericCount}.");
        }

        return (int) $count;
    }
}

<?php declare(strict_types=1);

namespace Tests\Utils\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\PreSaveArgResolver;

final class ConnectRelatedDirective extends BaseDirective implements PreSaveArgResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        directive @connectRelated(relation: String) on INPUT_FIELD_DEFINITION
        GRAPHQL;
    }

    /** @param Model $parent */
    public function __invoke($parent, $id): void
    {
        $relationName = $this->directiveArgValue('relation')
            ?? $this->nodeName();
        $relation = $parent->{$relationName}();
        assert($relation instanceof BelongsTo);

        $relation->associate($id);
    }
}

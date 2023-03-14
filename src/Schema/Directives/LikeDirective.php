<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class LikeDirective extends BaseDirective implements ArgBuilderDirective, FieldBuilderDirective
{
    public const ESCAPE = '\\';

    public const PERCENTAGE = '%';

    public const UNDERSCORE = '_';

    public const PLACEHOLDER = '{}';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Add a `LIKE` conditional to a database query.
"""
directive @like(
  """
  Specify the database column to compare.
  Required if the directive is:
  - used on an argument and the database column has a different name
  - used on a field
  """
  key: String

  """
  Fixate the positions of wildcards (`%`, `_`) in the LIKE comparison around the
  placeholder `{}`, e.g. `%{}`, `__{}` or `%{}%`.
  If specified, wildcard characters in the client-given input are escaped.
  If not specified, the client can pass wildcards unescaped.
  """
  template: String

  """
  Provide a value to compare against.
  Only used when the directive is added on a field.
  """
  value: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, $value): QueryBuilder|EloquentBuilder|Relation
    {
        if ($value === null) {
            return $builder;
        }

        $template = $this->directiveArgValue('template');
        if (is_string($template)) {
            $value = $this->fillTemplate($template, $value);
        }

        return $builder->where(
            $this->directiveArgValue('key', $this->nodeName()),
            'LIKE',
            $value,
        );
    }

    public function handleFieldBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): QueryBuilder|EloquentBuilder|Relation
    {
        return $this->handleBuilder(
            $builder,
            $this->directiveArgValue('value'),
        );
    }

    protected function fillTemplate(string $wildcardsTemplate, string $value): string
    {
        return str_replace(
            self::PLACEHOLDER,
            $this->escapeWildcards($value),
            $wildcardsTemplate,
        );
    }

    protected function escapeWildcards(string $value): string
    {
        return str_replace(
            [self::ESCAPE, self::PERCENTAGE, self::UNDERSCORE],
            [self::ESCAPE . self::ESCAPE, self::ESCAPE . self::PERCENTAGE, self::ESCAPE . self::UNDERSCORE],
            $value,
        );
    }
}

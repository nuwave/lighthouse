<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;

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

    /**
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function handleBuilder($builder, $value): object
    {
        if (null === $value) {
            return $builder;
        }

        $template = $this->directiveArgValue('template');
        if (is_string($template)) {
            $value = $this->fillTemplate($template, $value);
        }

        return $builder->where(
            $this->directiveArgValue('key', $this->nodeName()),
            'LIKE',
            $value
        );
    }

    public function handleFieldBuilder(object $builder): object
    {
        return $this->handleBuilder(
            $builder,
            $this->directiveArgValue('value')
        );
    }

    protected function fillTemplate(string $wildcardsTemplate, string $value): string
    {
        return str_replace(
            self::PLACEHOLDER,
            $this->escapeWildcards($value),
            $wildcardsTemplate
        );
    }

    protected function escapeWildcards(string $value): string
    {
        return str_replace(
            [self::ESCAPE, self::PERCENTAGE, self::UNDERSCORE],
            [self::ESCAPE . self::ESCAPE, self::ESCAPE . self::PERCENTAGE, self::ESCAPE . self::UNDERSCORE],
            $value
        );
    }
}

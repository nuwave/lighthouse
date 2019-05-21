<?php

namespace Nuwave\Lighthouse\WhereConstraints;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class WhereConstraintsDirective extends BaseDirective implements ArgBuilderDirective
{
    const NAME = 'whereConstraints';
    const INVALID_COLUMN_MESSAGE = 'Column names may contain only alphanumerics or underscores, and may not begin with a digit.';

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $whereConstraints
     * @param  bool  $nestedOr
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $whereConstraints, bool $nestedOr = false)
    {
        if ($andConnectedConstraints = $whereConstraints['AND'] ?? null) {
            $builder->whereNested(
                function ($builder) use ($andConnectedConstraints): void {
                    foreach ($andConnectedConstraints as $constraint) {
                        $this->handleBuilder($builder, $constraint);
                    }
                }
            );
        }

        if ($orConnectedConstraints = $whereConstraints['OR'] ?? null) {
            $builder->whereNested(
                function ($builder) use ($orConnectedConstraints): void {
                    foreach ($orConnectedConstraints as $constraint) {
                        $this->handleBuilder($builder, $constraint, true);
                    }
                }
            );
        }

        if ($notConnectedConstraints = $whereConstraints['NOT'] ?? null) {
            $builder->whereNested(
                function ($builder) use ($notConnectedConstraints): void {
                    foreach ($notConnectedConstraints as $constraint) {
                        $this->handleBuilder($builder, $constraint);
                    }
                },
                'not'
            );
        }

        if ($column = $whereConstraints['column'] ?? null) {
            if (! isset($whereConstraints['value'])) {
                throw new Error(
                    "Did not receive a value to match the WhereConstraints for column {$column}."
                );
            }

            if (! \Safe\preg_match('/^(?![0-9])[A-Za-z0-9_-]*$/', $column)) {
                throw new Error(
                    self::INVALID_COLUMN_MESSAGE
                );
            }

            $where = $nestedOr
                ? 'orWhere'
                : 'where';

            $builder->{$where}(
                $column,
                $whereConstraints['operator'],
                $whereConstraints['value']
            );
        }

        return $builder;
    }
}

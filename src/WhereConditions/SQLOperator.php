<?php

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Error\Error;

class SQLOperator implements Operator
{
    public static function missingValueForColumn(string $column): string
    {
        return "Did not receive a value to match the WhereConditions for column {$column}.";
    }

    public function enumDefinition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"The available SQL operators that are used to filter query results."
enum SQLOperator {
    "Equal operator (`=`)"
    EQ @enum(value: "=")

    "Not equal operator (`!=`)"
    NEQ @enum(value: "!=")

    "Greater than operator (`>`)"
    GT @enum(value: ">")

    "Greater than or equal operator (`>=`)"
    GTE @enum(value: ">=")

    "Less than operator (`<`)"
    LT @enum(value: "<")

    "Less than or equal operator (`<=`)"
    LTE @enum(value: "<=")

    "Simple pattern matching (`LIKE`)"
    LIKE @enum(value: "LIKE")

    "Negation of simple pattern matching (`NOT LIKE`)"
    NOT_LIKE @enum(value: "NOT_LIKE")

    "Whether a value is within a set of values (`IN`)"
    IN @enum(value: "In")

    "Whether a value is not within a set of values (`NOT IN`)"
    NOT_IN @enum(value: "NotIn")

    "Whether a value is within a range of values (`BETWEEN`)"
    BETWEEN @enum(value: "Between")

    "Whether a value is not within a range of values (`NOT BETWEEN`)"
    NOT_BETWEEN @enum(value: "NotBetween")

    "Whether a value is null (`IS NULL`)"
    IS_NULL @enum(value: "Null")

    "Whether a value is not null (`IS NOT NULL`)"
    IS_NOT_NULL @enum(value: "NotNull")
}
GRAPHQL;
    }

    public function default(): string
    {
        return 'EQ';
    }

    public function defaultHasOperator(): string
    {
        return 'GTE';
    }

    public function applyConditions($builder, array $whereConditions, string $boolean)
    {
        $column = $whereConditions['column'];

        // Laravel's conditions always start off with this prefix
        $method = 'where';

        // The first argument to conditions methods is always the column name
        $args = [$column];

        // Some operators require calling Laravel's conditions in different ways
        $operator = $whereConditions['operator'];
        $arity = $this->operatorArity($operator);

        if (3 === $arity) {
            // Usually, the operator is passed as the second argument to the condition
            // method, e.g. ->where('some_col', '=', $value)
            $args[] = $operator;
        } else {
            // We utilize the fact that the operators are named after Laravel's condition
            // methods so we can simply append the name, e.g. whereNull, whereNotBetween
            $method .= $operator;
        }

        if ($arity > 1) {
            // The conditions with arity 1 require no args apart from the column name.
            // All other arities take a value to query against.
            if (! array_key_exists('value', $whereConditions)) {
                throw new Error(
                    self::missingValueForColumn($column)
                );
            }

            $args[] = $whereConditions['value'];
        }

        // The condition methods always have the `$boolean` arg after the value
        $args[] = $boolean;

        return $builder->{$method}(...$args);
    }

    protected function operatorArity(string $operator): int
    {
        if (in_array($operator, ['Null', 'NotNull'])) {
            return 1;
        }

        if (in_array($operator, ['In', 'NotIn', 'Between', 'NotBetween'])) {
            return 2;
        }

        return 3;
    }
}

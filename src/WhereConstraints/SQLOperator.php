<?php

namespace Nuwave\Lighthouse\WhereConstraints;

use GraphQL\Error\Error;

class SQLOperator implements Operator
{
    public static function missingValueForColumn(string $column): string
    {
        return "Did not receive a value to match the WhereConstraints for column {$column}.";
    }

    public function enumDefinition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
enum SQLOperator {
    EQ @enum(value: "=")
    NEQ @enum(value: "!=")
    GT @enum(value: ">")
    GTE @enum(value: ">=")
    LT @enum(value: "<")
    LTE @enum(value: "<=")
    LIKE @enum(value: "LIKE")
    NOT_LIKE @enum(value: "NOT_LIKE")
    IN @enum(value: "In")
    NOT_IN @enum(value: "NotIn")
    BETWEEN @enum(value: "Between")
    NOT_BETWEEN @enum(value: "NotBetween")
    IS_NULL @enum(value: "Null")
    IS_NOT_NULL @enum(value: "NotNull")
}
GRAPHQL;
    }

    public function default(): string
    {
        return 'EQ';
    }

    /**
     * Apply the constraints to the query builder.
     *
     * @param  \Illuminate\Database\Query\Builder  $builder
     * @param  array  $whereConstraints
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder
     */
    public function applyConstraints($builder, array $whereConstraints, string $boolean)
    {
        $column = $whereConstraints['column'];

        // Laravel's conditions always start off with this prefix
        $method = 'where';

        // The first argument to conditions methods is always the column name
        $args[] = $column;

        // Some operators require calling Laravel's conditions in different ways
        $operator = $whereConstraints['operator'];
        $arity = $this->operatorArity($operator);

        if ($arity === 3) {
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
            if (! array_key_exists('value', $whereConstraints)) {
                throw new Error(
                    self::missingValueForColumn($column)
                );
            }

            $args[] = $whereConstraints['value'];
        }

        // The condition methods always have the `$boolean` arg after the value
        $args[] = $boolean;

        return call_user_func_array([$builder, $method], $args);
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

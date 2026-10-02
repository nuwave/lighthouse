<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class SQLOperator implements Operator
{
    public static function missingValueForColumn(string $column): Error
    {
        return new Error("Did not receive a value to match the WhereConditions for column {$column}.");
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
    NOT_LIKE @enum(value: "NOT LIKE")

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

    "Whether a value is a key in the JSON."
    JSON_CONTAINS_KEY @enum(value: "JsonContainsKey")

    "Whether a value is not a key in the JSON."
    JSON_DOESNT_CONTAIN_KEY @enum(value: "JsonDoesntContainKey")

    "Whether a value is in the JSON."
    JSON_CONTAINS @enum(value: "JsonContains")

    "Whether a value is not in the JSON."
    JSON_DOESNT_CONTAIN @enum(value: "JsonDoesntContain")

    "Whether a value is equals to the length of JSON array."
    JSON_LENGTH_EQ @enum(value: "JsonLengthEq")

    "Whether a value is not equals to the length of JSON array."
    JSON_LENGTH_NEQ @enum(value: "JsonLengthNeq")

    "Whether a value is less than the length of JSON array."
    JSON_LENGTH_LT @enum(value: "JsonLengthLt")

    "Whether a value is greater than the length of JSON array."
    JSON_LENGTH_GT @enum(value: "JsonLengthGt")

    "Whether a value is less than or equals to the length of JSON array."
    JSON_LENGTH_LTE @enum(value: "JsonLengthLte")

    "Whether a value is greater than or equals to the length of JSON array."
    JSON_LENGTH_GTE @enum(value: "JsonLengthGte")
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

    public function applyConditions(QueryBuilder|EloquentBuilder $builder, array $whereConditions, string $boolean): QueryBuilder|EloquentBuilder
    {
        $column = $whereConditions['column'];

        // Laravel's conditions always start off with this prefix
        $method = 'where';

        // The first argument to conditions methods is always the column name
        $args = [$column];

        // Some operators require calling Laravel's conditions in different ways
        $operator = $whereConditions['operator'];
        $arity = $this->operatorArity($operator);

        if (str_starts_with($operator, 'JsonLength')) {
            $operator = match($operator) {
                'JsonLengthEq' => '=',
                'JsonLengthNeq' => '!=',
                'JsonLengthLt' => '<',
                'JsonLengthGt' => '>',
                'JsonLengthLte' => '<=',
                'JsonLengthGte' => '>=',
            };

            $method = 'whereJsonLength';
        }

        if ($arity === 3) {
            // Usually, the operator is passed as the second argument to the condition
            // method, e.g. ->where('some_col', '=', $value)
            $args[] = $operator;
        } else {
            // We use the fact that the operators are named after Laravel's condition
            // methods, so we can simply append the name, e.g. whereNull, whereNotBetween
            $method .= $operator;
        }

        if ($arity > 1) {
            // The conditions with arity 1 require no args apart from the column name.
            // All other arities take a value to query against.
            if (! array_key_exists('value', $whereConditions)) {
                throw self::missingValueForColumn($column);
            }

            $args[] = $whereConditions['value'];
        }

        // The condition methods always have the `$boolean` arg after the value
        $args[] = $boolean;

        return $builder->{$method}(...$args);
    }

    protected function operatorArity(string $operator): int
    {
        if (in_array($operator, ['Null', 'NotNull', 'JsonContainsKey', 'JsonDoesntContainKey'], strict: true)) {
            return 1;
        }

        if (in_array($operator, ['In', 'NotIn', 'Between', 'NotBetween', 'JsonContains', 'JsonDoesntContain'], strict: true)) {
            return 2;
        }

        return 3;
    }
}

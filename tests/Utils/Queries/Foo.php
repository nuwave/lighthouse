<?php

namespace Tests\Utils\Queries;

/**
 * This is used solely as a placeholder resolver, as schemas without a valid
 * field in the query type are invalid.
 */
class Foo
{
    /**
     * The answer to life, the universe and everything.
     *
     * @var int
     */
    public const THE_ANSWER = 42;

    /**
     * Return a value for the field.
     */
    public function __invoke(): int
    {
        return self::THE_ANSWER;
    }

    /**
     * Calculate the complexity.
     */
    public function complexity(): int
    {
        return self::THE_ANSWER;
    }
}

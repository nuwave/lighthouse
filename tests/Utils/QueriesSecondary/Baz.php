<?php

namespace Tests\Utils\QueriesSecondary;

class Baz
{
    /**
     * The answer to life, the universe and everything.
     *
     * @var int
     */
    const THE_ANSWER = 42;

    /**
     * Return a value for the field.
     *
     * @return int
     */
    public function resolve(): int
    {
        return self::THE_ANSWER;
    }
}

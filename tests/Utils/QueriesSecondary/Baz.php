<?php

namespace Tests\Utils\QueriesSecondary;

class Baz
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
}

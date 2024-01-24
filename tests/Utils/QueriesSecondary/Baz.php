<?php declare(strict_types=1);

namespace Tests\Utils\QueriesSecondary;

final class Baz
{
    /** The answer to life, the universe and everything. */
    public const THE_ANSWER = 42;

    /** Return a value for the field. */
    public function __invoke(): int
    {
        return self::THE_ANSWER;
    }
}

<?php declare(strict_types=1);

namespace Tests\Utils\QueriesSecondary;

final class Foo
{
    /** War is not the answer. */
    public const NOT_THE_ANSWER = 'war';

    /** Return a value for the field. */
    public function __invoke(): string
    {
        return self::NOT_THE_ANSWER;
    }
}

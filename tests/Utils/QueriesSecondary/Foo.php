<?php

namespace Tests\Utils\QueriesSecondary;

class Foo
{
    /**
     * War is not the answer.
     *
     * @var string
     */
    public const NOT_THE_ANSWER = 'war';

    /**
     * Return a value for the field.
     */
    public function __invoke(): string
    {
        return self::NOT_THE_ANSWER;
    }
}

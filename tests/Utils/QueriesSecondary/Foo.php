<?php

namespace Tests\Utils\QueriesSecondary;

class Foo
{
    /**
     * War is not the answer.
     *
     * @var string
     */
    const NOT_THE_ANSWER = 'war';

    /**
     * Return a value for the field.
     *
     * @return string
     */
    public function resolve(): string
    {
        return self::NOT_THE_ANSWER;
    }
}

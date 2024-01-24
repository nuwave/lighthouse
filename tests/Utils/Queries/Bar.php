<?php declare(strict_types=1);

namespace Tests\Utils\Queries;

final class Bar
{
    public const RESULT = 'foobaz';

    public function __invoke(): string
    {
        return self::RESULT;
    }
}

<?php declare(strict_types=1);

namespace Tests\Utils\Types\User;

final class NonRootClassResolver
{
    public const RESULT = 'this is the unique result of calling the resolver class ' . self::class;

    public function __invoke(): string
    {
        return self::RESULT;
    }
}

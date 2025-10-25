<?php declare(strict_types=1);

namespace Tests\Utils\Mutations;

use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class ThrowWhenInvoked
{
    public const ERROR_MESSAGE = 'Custom error message from ThrowWhenInvoked mutation.';

    /**
     * @param  array<string, mixed>  $args
     *
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args): array
    {
        throw new AuthorizationException(self::ERROR_MESSAGE);
    }
}

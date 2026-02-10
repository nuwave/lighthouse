<?php declare(strict_types=1);

namespace Tests\Utils\Exceptions;

use GraphQL\Error\ClientAware;

final class ClientAwareException extends \Exception implements ClientAware
{
    private function __construct(
        private bool $clientSafe,
    ) {
        parent::__construct('Client Aware Error');
    }

    public static function clientSafe(): self
    {
        return new self(true);
    }

    public static function notClientSafe(): self
    {
        return new self(false);
    }

    public function isClientSafe(): bool
    {
        return $this->clientSafe;
    }
}

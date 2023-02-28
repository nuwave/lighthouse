<?php declare(strict_types=1);

namespace Tests\Utils\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

/**
 * @phpstan-type ExtensionsContent array<string, mixed>
 */
final class WithExtensionsException extends \Exception implements ClientAware, ProvidesExtensions
{
    /**
     * @param  ExtensionsContent  $extensionsContent
     */
    public function __construct(string $message, protected array $extensionsContent)
    {
        parent::__construct($message);
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * @return ExtensionsContent
     */
    public function getExtensions(): array
    {
        return $this->extensionsContent;
    }
}

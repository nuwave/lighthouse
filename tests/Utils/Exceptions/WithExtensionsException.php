<?php

namespace Tests\Utils\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

/**
 * @phpstan-type ExtensionsContent array<string, mixed>
 */
final class WithExtensionsException extends \Exception implements ClientAware, ProvidesExtensions
{
    /**
     * @var ExtensionsContent
     */
    protected $extensionsContent;

    /**
     * @param  ExtensionsContent  $extensionsContent
     */
    public function __construct(string $message, array $extensionsContent)
    {
        parent::__construct($message);

        $this->extensionsContent = $extensionsContent;
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

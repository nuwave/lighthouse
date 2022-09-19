<?php

namespace Tests\Utils\Exceptions;

use GraphQL\Error\ClientAware;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;

final class WithExtensionsException extends \Exception implements ClientAware, RendersErrorsExtensions
{
    /**
     * @var array<string, mixed>
     */
    protected $extensionsContent;

    /**
     * @param  array<string, mixed>  $extensionsContent
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

    public function getCategory(): string
    {
        return 'literally no one cares';
    }

    public function extensionsContent(): array
    {
        return $this->extensionsContent;
    }
}

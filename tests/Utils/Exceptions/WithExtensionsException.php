<?php

namespace Tests\Utils\Exceptions;

use GraphQL\Error\ClientAware;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;

class WithExtensionsException extends \Exception implements ClientAware, RendersErrorsExtensions
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

    public function extensionsContent(): array
    {
        return $this->extensionsContent;
    }
}

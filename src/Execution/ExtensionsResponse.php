<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

/**
 * May be returned from listeners of @see \Nuwave\Lighthouse\Events\BuildExtensionsResponse.
 */
class ExtensionsResponse
{
    public function __construct(
        /**
         * Will be used as the key in the response map.
         */
        protected string $key,
        /**
         * JSON-encodable content of the extension.
         */
        protected mixed $content,
    ) {}

    /**
     * Return the key of the extension.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return mixed JSON-encodable content of the extension
     */
    public function content()
    {
        return $this->content;
    }
}

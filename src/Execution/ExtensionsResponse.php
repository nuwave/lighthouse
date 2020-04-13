<?php

namespace Nuwave\Lighthouse\Execution;

/**
 * May be returned from listeners of the event:.
 * @see \Nuwave\Lighthouse\Events\BuildExtensionsResponse
 */
class ExtensionsResponse
{
    /**
     * Will be used as the key in the response map.
     *
     * @var string
     */
    protected $key;

    /**
     * JSON-encodable content of the extension.
     */
    protected $content;

    /**
     * ExtensionsResponse constructor.
     *
     * @return void
     */
    public function __construct(string $key, $content)
    {
        $this->key = $key;
        $this->content = $content;
    }

    /**
     * Return the key of the extension.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Return the JSON-encodable content of the extension.
     */
    public function content()
    {
        return $this->content;
    }
}

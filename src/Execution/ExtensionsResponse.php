<?php

namespace Nuwave\Lighthouse\Execution;

/**
 * May be returned from listeners of @see \Nuwave\Lighthouse\Events\BuildExtensionsResponse.
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
     * @var mixed JSON-encodable content of the extension
     */
    protected $content;

    /**
     * @param  mixed  $content  JSON-encodable content
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
     * @return mixed JSON-encodable content of the extension
     */
    public function content()
    {
        return $this->content;
    }
}

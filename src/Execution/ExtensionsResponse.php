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
     *
     * @var mixed
     */
    protected $content;

    /**
     * ExtensionsResponse constructor.
     *
     * @param  string  $key
     * @param  mixed  $content
     * @return void
     */
    public function __construct(string $key, $content)
    {
        $this->key = $key;
        $this->content = $content;
    }

    /**
     * Return the key of the extension.
     *
     * @return string
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Return the JSON-encodable content of the extension.
     *
     * @return mixed
     */
    public function content()
    {
        return $this->content;
    }
}

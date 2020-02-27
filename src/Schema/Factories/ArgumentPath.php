<?php

namespace Nuwave\Lighthouse\Schema\Factories;

class ArgumentPath
{
    /** @var array<string|int> */
    protected $segments = [];

    public function __construct()
    {
    }

    public function add($pathSegment)
    {
        $this->segments [] = $pathSegment;
    }

    public function path(): string
    {
        return implode('.', $this->segments);
    }

    public function wrap(array $rulesOrMessages)
    {
        $withPath = [];

        foreach ($rulesOrMessages as $key => $value) {
            $withPath[$this->path().$key] = $value;
        }

        return $withPath;
    }
}

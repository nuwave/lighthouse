<?php

namespace Nuwave\Lighthouse\CacheControl;

class CacheControl
{
    /**
     * Maximum age.
     *
     * @var int|null
     */
    public $maxAge = null;

    /**
     * Is the result public?
     *
     * @var bool
     */
    protected $public = true;

    public function addMaxAge(int $maxAge): void
    {
        $this->maxAge = isset($this->maxAge)
            ? min($maxAge, $this->maxAge)
            : $maxAge;
    }

    public function setPrivate(): void
    {
        $this->public = false;
    }

    public function maxAge(): int
    {
        return $this->maxAge ?? 0;
    }

    public function scope(): string
    {
        return $this->public
            ? 'public'
            : 'private';
    }
}

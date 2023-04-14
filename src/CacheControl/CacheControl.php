<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\CacheControl;

class CacheControl
{
    /** Maximum age. */
    public ?int $maxAge = null;

    /** Is the result public? */
    protected bool $public = true;

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

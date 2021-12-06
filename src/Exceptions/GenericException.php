<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\Error;

class GenericException extends Error
{
    protected $category = 'generic';

    /**
     * @param  array<string, mixed>  $extensions
     */
    public function setExtensions(array $extensions): self
    {
        $this->extensions = $extensions;

        return $this;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }
}

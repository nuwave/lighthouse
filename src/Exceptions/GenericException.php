<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\Error;

class GenericException extends Error
{
    protected $category = 'generic';

    /**
     * @param $extensions
     *
     * @return static
     */
    public function setExtensions($extensions): self
    {
        $this->extensions = (array) $extensions;

        return $this;
    }

    /**
     * @param string $category
     *
     * @return static
     */
    public function setCategory(string $category)
    {
        $this->category = $category;

        return $this;
    }
}

<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\Error;

class GenericException extends Error
{
    /**
     * The category.
     *
     * @var string
     */
    protected $category = 'generic';

    /**
     * Set the contents that will be rendered under the "extensions" key of the error response.
     *
     * @param  array<mixed, mixed>  $extensions
     * @return $this
     */
    public function setExtensions(array $extensions): self
    {
        $this->extensions = $extensions;

        return $this;
    }

    /**
     * Set the category that will be rendered under the "extensions" key of the error response.
     *
     * @return $this
     */
    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }
}

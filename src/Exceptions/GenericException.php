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
        // TODO remove this wrapping as we switch to the new version of webonyx/graphql-php
        // The underlying issue is already resolved there. For now, this fix will do
        // and make sure we return a spec compliant error.
        // https://github.com/webonyx/graphql-php/commit/f4008f0fb2294178fc0ecc3f7a7f71a13b543db1
        $this->extensions = ['extensions' => (array) $extensions];

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

<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

interface HasRootArgumentValue
{
    /**
     * @return ArgumentValue
     */
    public function rootArgumentValue(): ArgumentValue;

    /**
     * @param ArgumentValue $rootArgumentValue
     *
     * @return static
     */
    public function setRootArgumentValue(ArgumentValue $rootArgumentValue);
}

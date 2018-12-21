<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

trait HasRootArgumentValue
{
    /**
     * @var ArgumentValue
     */
    protected $rootArgumentValue;

    /**
     * @return ArgumentValue
     */
    public function rootArgumentValue(): ArgumentValue
    {
        return $this->rootArgumentValue;
    }

    /**
     * @param ArgumentValue $rootArgumentValue
     *
     * @return static
     */
    public function setRootArgumentValue(ArgumentValue $rootArgumentValue)
    {
        $this->rootArgumentValue = $rootArgumentValue;

        return $this;
    }
}

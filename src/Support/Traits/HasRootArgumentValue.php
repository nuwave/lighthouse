<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

trait HasRootArgumentValue
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Values\ArgumentValue
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
     * @param  ArgumentValue  $rootArgumentValue
     *
     * @return $this
     */
    public function setRootArgumentValue(ArgumentValue $rootArgumentValue): self
    {
        $this->rootArgumentValue = $rootArgumentValue;

        return $this;
    }
}

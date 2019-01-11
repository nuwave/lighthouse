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
     * @return \Nuwave\Lighthouse\Schema\Values\ArgumentValue
     */
    public function rootArgumentValue(): ArgumentValue
    {
        return $this->rootArgumentValue;
    }

    /**
     * @param  \Nuwave\Lighthouse\Schema\Values\ArgumentValue  $rootArgumentValue
     * @return $this
     */
    public function setRootArgumentValue(ArgumentValue $rootArgumentValue): self
    {
        $this->rootArgumentValue = $rootArgumentValue;

        return $this;
    }
}

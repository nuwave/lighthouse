<?php


namespace Nuwave\Lighthouse\Schema\Traits;


use Nuwave\Lighthouse\Schema\Contracts\TypeWrapper;
use Nuwave\Lighthouse\Types\Type;

trait HasName
{
    public $name;

    public function name() : ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return self|Type
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Gets the name of the type even if it's wrapped in
     * non-null or list.
     *
     * @return null|string
     */
    public function getUnderlyingName() : ?string
    {
        if($this instanceof TypeWrapper) {
            return $this->getUnderlyingType()->name();
        }
        return $this->name();
    }
}
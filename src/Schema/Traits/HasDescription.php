<?php


namespace Nuwave\Lighthouse\Schema\Traits;


use Nuwave\Lighthouse\Schema\Contracts\TypeWrapper;
use Nuwave\Lighthouse\Types\Type;

trait HasDescription
{
    public $description;

    public abstract function manipulatable();

    public function description() : ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return self|Type
     */
    public function setDescription(?string $description): self
    {
        $this->manipulatable();
        $this->description = $description;
        return $this;
    }

    /**
     * Gets the Description of the type even if it's wrapped in
     * non-null or list.
     *
     * @return null|string
     */
    public function getUnderlyingDescription() : ?string
    {
        if($this instanceof TypeWrapper) {
            return $this->getUnderlyingType()->description();
        }
        return $this->description();
    }
}
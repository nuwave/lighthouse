<?php

namespace Nuwave\Lighthouse\Support\Traits;

trait RelayModel
{
    /**
     * ID Attribute mutator.
     *
     * Note: Can be used if your Eloquent model doesn't
     * have an id field.
     *
     * @return int
     */
    public function getIdAttribute()
    {
        return $this->attributes[$this->getKeyName()];
    }
}

<?php


namespace Nuwave\Lighthouse\Schema\Traits;


use Nuwave\Lighthouse\Support\Exceptions\NotManipulatable;

trait CanManipulate
{
    protected $isLocked;

    public function canManipulate() : bool
    {
        return !$this->isLocked;
    }

    /**
     * @throws NotManipulatable
     */
    public function manipulatable()
    {
        if(!$this->canManipulate()) {
            throw new NotManipulatable();
        }
    }

    public function doneManipulate()
    {
        $this->isLocked = true;
    }
}
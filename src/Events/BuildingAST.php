<?php

namespace Nuwave\Lighthouse\Events;

class BuildingAST
{
    /**
     * The root schema that was defined by the user.
     *
     * @var string
     */
    public $userSchema;
    
    /**
     * BuildingAST constructor.
     *
     * @param string $userSchema
     */
    public function __construct(string $userSchema)
    {
        $this->userSchema = $userSchema;
    }
}

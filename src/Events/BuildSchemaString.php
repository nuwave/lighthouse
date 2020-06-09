<?php

namespace Nuwave\Lighthouse\Events;

/**
 * Fires before building the AST from the user-defined schema string.
 *
 * Listeners may return a schema string,
 * which is added to the user schema.
 *
 * Only fires once if schema caching is active.
 */
class BuildSchemaString
{
    /**
     * The root schema that was defined by the user.
     *
     * @var string
     */
    public $userSchema;

    public function __construct(string $userSchema)
    {
        $this->userSchema = $userSchema;
    }
}

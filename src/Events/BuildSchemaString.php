<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

/**
 * Fires before building the AST from the user-defined schema string.
 *
 * Listeners may return a schema string, which is added to the user schema.
 *
 * Only fires once if schema caching is active.
 */
class BuildSchemaString
{
    public function __construct(
        /**
         * The root schema that was defined by the user.
         */
        public string $userSchema,
    ) {}
}

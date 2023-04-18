<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

class NamedType
{
    /** Is this type defined to be non-nullable? */
    public bool $nonNull = false;

    public function __construct(
        /**
         * The name of the type as defined in the schema.
         */
        public string $name,
    ) {}
}

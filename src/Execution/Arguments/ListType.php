<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

class ListType
{
    /** Is the list itself defined to be non-nullable? */
    public bool $nonNull = false;

    public function __construct(
        public ListType|NamedType $type,
    ) {}
}

<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use Nuwave\Lighthouse\Support\Contracts\GlobalId as GlobalIdContract;

class GlobalId implements GlobalIdContract
{
    /**
     * {@inheritDoc}
     */
    public function encode(string $type, $id): string
    {
        return base64_encode($type.':'.$id);
    }

    /**
     * {@inheritDoc}
     */
    public function decodeID(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return $id;
    }

    /**
     * {@inheritDoc}
     */
    public function decode(string $globalID): array
    {
        return explode(':', base64_decode($globalID));
    }

    /**
     * {@inheritDoc}
     */
    public function decodeType(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return $type;
    }
}

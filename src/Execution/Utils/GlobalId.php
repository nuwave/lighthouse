<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use Nuwave\Lighthouse\Support\Contracts\GlobalId as GlobalIdContract;

class GlobalId implements GlobalIdContract
{
    /**
     * {@inheritdoc}
     */
    public function encode(string $type, $id): string
    {
        return base64_encode($type.':'.$id);
    }

    /**
     * {@inheritdoc}
     */
    public function decode(string $globalID): array
    {
        return explode(':', base64_decode($globalID));
    }

    /**
     * {@inheritdoc}
     */
    public function decodeID(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function decodeType(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return $type;
    }
}

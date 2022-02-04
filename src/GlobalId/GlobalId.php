<?php

namespace Nuwave\Lighthouse\GlobalId;

use Nuwave\Lighthouse\Support\Contracts\GlobalId as GlobalIdContract;

/**
 * The default encoding of global IDs in Lighthouse.
 *
 * The way that IDs are generated basically works like this:
 *
 * 1. Take the name of a type, e.g. "User" and an ID, e.g. 123
 * 2. Glue them together, separated by a colon, e.g. "User:123"
 * 3. base64_encode the result
 *
 * This can then be reversed to uniquely identify an entity in our
 * schema, just by looking at a single ID.
 */
class GlobalId implements GlobalIdContract
{
    public function encode(string $type, $id): string
    {
        return base64_encode($type . ':' . $id);
    }

    public function decode(string $globalID): array
    {
        $parts = explode(':', \Safe\base64_decode($globalID));

        if (2 !== count($parts)) {
            throw new GlobalIdException("Unexpectedly found more then 2 segments when decoding global id: {$globalID}.");
        }

        /** @var array{0: string, 1: string} $parts */
        return $parts;
    }

    public function decodeID(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return trim($id);
    }

    public function decodeType(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return trim($type);
    }
}

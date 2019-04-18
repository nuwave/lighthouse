<?php

namespace Nuwave\Lighthouse\Execution\Utils;

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
    /**
     * Glue together a type and an id to create a global id.
     *
     * @param  string  $type
     * @param  string|int  $id
     * @return string
     */
    public function encode(string $type, $id): string
    {
        return base64_encode($type.':'.$id);
    }

    /**
     * Split a global id into the type and the id it contains.
     *
     * @param  string  $globalID
     * @return array Contains [$type, $id], e.g. ['User', '123']
     */
    public function decode(string $globalID): array
    {
        return explode(':', base64_decode($globalID));
    }

    /**
     * Decode the Global ID and get just the ID.
     *
     * @param  string  $globalID
     * @return string
     */
    public function decodeID(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return $id;
    }

    /**
     * Decode the Global ID and get just the type.
     *
     * @param  string  $globalID
     * @return string
     */
    public function decodeType(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return $type;
    }
}

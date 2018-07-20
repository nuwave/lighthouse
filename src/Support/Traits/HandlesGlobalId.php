<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Schema\Execution\Utils\GlobalIdUtil;

/**
 * @deprecated use the GlobalIdUtil class instead, this trait
 * will be removed in the next version
 */
trait HandlesGlobalId
{
    /**
     * Create global id.
     *
     * @param string     $type
     * @param string|int $id
     *
     * @return string
     */
    public function encodeGlobalId($type, $id)
    {
        return GlobalIdUtil::encodeGlobalId($type, $id);
    }

    /**
     * Decode the global id.
     *
     * @param string $id
     *
     * @return array
     */
    public function decodeGlobalId($id)
    {
        return GlobalIdUtil::decodeGlobalId($id);
    }

    /**
     * Get the decoded id.
     *
     * @param string $id
     *
     * @return string
     */
    public function decodeRelayId($id)
    {
        return GlobalIdUtil::decodeRelayId($id);
    }

    /**
     * Get the decoded GraphQL Type.
     *
     * @param string $id
     *
     * @return string
     */
    public function decodeRelayType($id)
    {
        return GlobalIdUtil::decodeRelayType($id);
    }

    /**
     * Decode cursor from query arguments.
     *
     * @param array $args
     *
     * @return int
     */
    protected function decodeCursor(array $args)
    {
        return GlobalIdUtil::decodeCursor($args);
    }

    /**
     * Get id from encoded cursor.
     *
     * @param string $cursor
     *
     * @return int
     */
    protected function getCursorId($cursor)
    {
        return GlobalIdUtil::getCursorId($cursor);
    }
}

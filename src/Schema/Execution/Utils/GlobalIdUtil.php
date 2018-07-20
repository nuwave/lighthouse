<?php

namespace Nuwave\Lighthouse\Schema\Execution\Utils;

class GlobalIdUtil
{
    /**
     * Create global id.
     *
     * @param string     $type
     * @param string|int $id
     *
     * @return string
     */
    public static function encodeGlobalId($type, $id)
    {
        $resolver = config('lighthouse.globalId.encode');

        if (is_callable($resolver)) {
            return $resolver($type, $id);
        }

        return base64_encode($type.':'.$id);
    }

    /**
     * Decode the global id.
     *
     * @param string $id
     *
     * @return array
     */
    public static function decodeGlobalId($id)
    {
        return explode(':', base64_decode($id));
    }

    /**
     * Get the decoded id.
     *
     * @param string $id
     *
     * @return string
     */
    public static function decodeRelayId($id)
    {
        $resolver = config('lighthouse.globalId.decodeId');

        if (is_callable($resolver)) {
            return $resolver($id);
        }

        list($type, $id) = self::decodeGlobalId($id);

        return $id;
    }

    /**
     * Get the decoded GraphQL Type.
     *
     * @param string $id
     *
     * @return string
     */
    public static function decodeRelayType($id)
    {
        $resolver = config('lighthouse.globalId.decodeType');

        if (is_callable($resolver)) {
            return $resolver($id);
        }

        list($type, $id) = self::decodeGlobalId($id);

        return $type;
    }

    /**
     * Decode cursor from query arguments.
     *
     * @param array $args
     *
     * @return int
     */
    public static function decodeCursor(array $args)
    {
        $resolver = config('lighthouse.globalId.decodeCursor');

        if (is_callable($resolver)) {
            return $resolver($args);
        }

        return isset($args['after']) && ! empty($args['after'])
            ? self::getCursorId($args['after'])
            : 0;
    }

    /**
     * Get id from encoded cursor.
     *
     * @param string $cursor
     *
     * @return int
     */
    public static function getCursorId($cursor)
    {
        $resolver = config('lighthouse.globalId.getCursorId');

        if (is_callable($resolver)) {
            return $resolver($cursor);
        }

        return (int) self::decodeRelayId($cursor);
    }
}

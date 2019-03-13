<?php

namespace Nuwave\Lighthouse\Events;

/**
 * Fires after a query was resolved.
 *
 * Listeners of this event must return an array comprised of
 * a single key and the extension content as the value, e.g.
 * ['tracing' => ['some' => 'content']]
 */
class BuildExtensionsResponse
{
    //
}

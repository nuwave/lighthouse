<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

/**
 * Fires after a query was resolved.
 *
 * Listeners may return a @see \Nuwave\Lighthouse\Execution\ExtensionsResponse
 * to include in the response.
 */
class BuildExtensionsResponse {}

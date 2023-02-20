<?php

namespace Nuwave\Lighthouse\Events;

/**
 * Fires when the directive factory is constructed.
 *
 * Listeners may return namespaces in the form of either:
 * - a single string
 * - an iterable of multiple strings
 * The returned namespaces will be used as the search base for locating directives.
 *
 * @see \Nuwave\Lighthouse\Schema\DirectiveLocator::namespaces()
 */
class RegisterDirectiveNamespaces
{
}

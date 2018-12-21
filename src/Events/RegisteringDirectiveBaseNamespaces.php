<?php

namespace Nuwave\Lighthouse\Events;

use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

/**
 * This event is sent when the directive factory is constructed.
 *
 * Listeners may return one or more strings that are used as the base
 * namespace for locating directives.
 *
 * @see DirectiveFactory
 */
class RegisteringDirectiveBaseNamespaces
{
}

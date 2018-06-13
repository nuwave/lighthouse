<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\Directive;

/**
 * Class NamespaceDirective.
 *
 * This directive class is just used for allowing this directive
 * to exist and to get it from the directive registry. Its purpose
 * is to provide namespaces for other directives. On its own, it does
 * not do anything.
 *
 * The args for this directive are map from directive names to namespaces.
 * For example: (field: "App\\GraphQL")
 *
 * The namespaces are used to complete class references in other field directives.
 */
class NamespaceDirective extends AbstractFieldDirective implements Directive
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'namespace';
    }
}

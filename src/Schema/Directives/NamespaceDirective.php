<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\Directive;

/**
 * Class NamespaceDirective.
 *
 * This directive class is just used for allowing this directive
 * to exist and to get it from the directive registry. Its purpose
 * is to provide namespaces for other directives. On its own, it does
 * not do anything.
 *
 * The args for this directive are a map from directive names to namespaces.
 * For example: @namespace(field: "App\\GraphQL")
 *
 * The namespaces are used to complete class references in other field directives.
 */
class NamespaceDirective implements Directive
{
    /**
     * todo remove as soon as name() is static itself.
     * @var string
     */
    const NAME = 'namespace';

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }
}

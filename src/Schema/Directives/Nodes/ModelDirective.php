<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;

class ModelDirective implements NodeMiddleware
{
    use CanParseTypes;

    /**
     * Directive name.
     *
     * @return string
     */
    public function name()
    {
        return 'model';
    }

    /**
     * Handle type construction.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function handle(NodeValue $value)
    {
        // 1. Create query types for model...
        $schemaTxt = '
        input {{model}}WhereNotNull {

        }
        input {{model}}InputType {}
        ';

        $schema = str_replace('{{model}}', $value->getType()->name, $schemaTxt);

        collect($this->getInputTypes($this->parseSchema($schema)))
            ->each(function ($type) {
                schema()->type($type);
            });
        // 2. Register node resolver w/ schema...
        return $value;
    }
}

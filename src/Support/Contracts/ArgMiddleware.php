<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\InputValueDefinitionNode;

interface ArgMiddleware
{
    /**
     * Resolve the field directive.
     *
     * @param InputValueDefinitionNode $arg
     * @param DirectiveNode            $directive
     * @param array                    $value
     *
     * @return array
     */
    public function handle(InputValueDefinitionNode $arg, DirectiveNode $directive, array $value);
}

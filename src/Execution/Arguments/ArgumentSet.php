<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Directives\SpreadDirective;

class ArgumentSet
{
    /**
     * An associative array from argument names to arguments.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\Argument[]
     */
    public $arguments = [];

    /**
     * A list of directives.
     *
     * This may be coming from the field the arguments are a part of
     * or the parent argument when in a tree of nested inputs.
     *
     * @var \GraphQL\Language\AST\DirectiveNode[]
     */
    public $directives = [];

    /**
     * Get a plain array representation of this ArgumentSet.
     *
     * @return array
     */
    public function toArray(): array
    {
        $plainArguments = [];

        foreach($this->arguments as $name => $argument) {
            $plainArguments[$name] = $argument->toPlain();
        }

        return $plainArguments;
    }

    /**
     * Apply the @spread directive and return a new instance.
     *
     * @return self
     */
    public function spread(): self
    {
        $argumentSet = new self();
        $argumentSet->directives = $this->directives;

        foreach ($this->arguments as $name => $argument) {
            $value = $argument->value;

            if ($value instanceof self) {
                // Recurse down first, as that resolves the more deeply nested spreads first
                $value = $value->spread();

                $directiveNode = ASTHelper::firstByName($argument->directives, SpreadDirective::NAME);
                if ($directiveNode) {
                    $argumentSet->arguments += $value->arguments;
                    continue;
                }
            }

            $argumentSet->arguments[$name] = $argument;
        }

        return $argumentSet;
    }
}

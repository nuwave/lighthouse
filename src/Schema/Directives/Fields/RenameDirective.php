<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class RenameDirective implements FieldResolver
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'rename';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldDefinitionNode $field
     *
     * @return \Closure
     */
    public function handle(FieldDefinitionNode $field)
    {
        $attribute = $this->directiveArgValue(
            $this->fieldDirective($field, $this->name()),
            'attribute'
        );

        if (! $attribute) {
            throw new DirectiveException(sprintf(
                'The [%s] directive requires an `attribute` argument.',
                $this->name()
            ));
        }

        return function ($parent, array $args) use ($attribute) {
            return data_get($parent, $attribute);
        };
    }
}

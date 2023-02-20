<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class KeyDirective extends BaseDirective
{
    public const NAME = 'key';

    /**
     * @see https://www.apollographql.com/docs/apollo-server/federation/federation-spec/#schema-modifications-glossary
     */
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
The @key directive is used to indicate a combination of fields that
can be used to uniquely identify and fetch an object or interface.
"""
directive @key(
    """
    Fields that can be used to uniquely identify and fetch an object or interface.
    """
    fields: _FieldSet!
) repeatable on OBJECT | INTERFACE
GRAPHQL;
    }

    public function fields(): SelectionSetNode
    {
        $fields = $this->directiveArgValue('fields');
        if (! is_string($fields)) {
            throw new DefinitionException("Argument `fields` on the `@{$this->name()}` directive is required.");
        }

        // Grammatically, a field set is a selection set minus the braces.
        // https://www.apollographql.com/docs/federation/federation-spec/#scalar-_fieldset
        return Parser::selectionSet("{ {$fields} }");
    }
}

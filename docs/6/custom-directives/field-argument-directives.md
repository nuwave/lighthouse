# Field Argument Directives

Field argument directives can be applied to field arguments (see [Field Arguments](https://spec.graphql.org/June2018/#sec-Field-Arguments)).

## ArgManipulator

An [`\Nuwave\Lighthouse\Support\Contracts\ArgManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ArgManipulator.php)
directive can be used to manipulate the schema AST of a field argument or its parents.

For example, you might want to add a directive that automagically derives the arguments
for a field based on an object type. A skeleton for this directive might look something like this:

```php
namespace App\GraphQL\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;

final class ModelArgsDirective extends BaseDirective implements ArgManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Automatically generates an input argument based on a type.
"""
directive @typeToInput(
    """
    The name of the type to use as the basis for the input type.
    """
    name: String!
) on ARGUMENT_DEFINITION
GRAPHQL;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        $typeName = $this->directiveArgValue('name');
        $type = $documentAST->types[$typeName];

        $input = $this->generateInputFromType($type);
        $argDefinition->name->value = $input->value->name;

        $documentAST->setTypeDefinition($input);
    }

    protected function generateInputFromType(ObjectTypeDefinitionNode $type): InputObjectTypeDefinitionNode
    {
        // TODO generate this type based on rules and conventions that work for you
    }
}
```

# Input Field Directives

Input field directives can be (similarly to [Argument Directives](argument-directives.html)) applied to a [InputValueDefinition](https://graphql.github.io/graphql-spec/June2018/#InputValueDefinition). In contrast to argument directives, input field directives can be set on fields of an input. 

## InputFieldManipulator

In order for an input field directive to work you must implement the [`\Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/InputFieldManipulator.php) interface.

```php
namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;
use GraphQL\Language\Parser;

final class ModelIdDirective extends BaseDirective implements InputFieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Append Id to name of input field and change the type to ID.
"""
directive @modelId on INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateInputFieldDefinition(
      DocumentAST &$documentAST,
      InputValueDefinitionNode &$inputDefinition,
      InputObjectTypeDefinitionNode &$parentType,
    ): void {
      $inputDefinition->name->value = $inputDefinition->name->value . 'Id';
      $inputDefinition->type = Parser::namedType('ID');
    }
}
```

```graphql
input Payload {
  post: Post @modelId
}

type Mutation {
  createComment(
    payload: Payload
  ): User
}
```

In the given example, the name of the input field will be renamed to `postId` and its type will be changed to `ID`. 

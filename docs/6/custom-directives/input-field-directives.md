# Input Field Directives

Input field directives can be applied to input fields (see [InputFieldsDefinition](https://spec.graphql.org/June2018/#InputFieldsDefinition)).

## InputFieldManipulator

An [`Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/InputFieldManipulator.php) directive can be used to manipulate the schema AST of an input field or its parent.

For example, the following directive automatically adds translations for the input field description.

```php
namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;
use GraphQL\Language\Parser;

final class TranslateDescriptionDirective extends BaseDirective implements InputFieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
        """
        Extends the description with automatic translations.
        """
        directive @translateDescription on INPUT_FIELD_DEFINITION
        GRAPHQL;
    }

    public function manipulateInputFieldDefinition(
      DocumentAST &$documentAST,
      InputValueDefinitionNode &$inputField,
      InputObjectTypeDefinitionNode &$parentInput,
    ): void {
        $inputField->description = implode('\n\n', [
            $inputField->description,
            \Translate::spanish($inputField->description),
            \Translate::german($inputField->description),
        ]);
    }
}
```

```diff
input CreateCommentInput {
- "Very nice."
+ """
+ Very nice.
+
+ Muy bien.
+
+ Sehr gut.
+ """
  content: String! @translateDescription
}
```

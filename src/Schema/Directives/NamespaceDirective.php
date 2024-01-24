<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

/**
 * Redefine the default namespaces used in other directives.
 *
 * The args for this directive are a map from directive names to namespaces.
 * For example `@namespace(field: "App\\GraphQL")` applies the namespace
 * `App\GraphQL` to the `@field` directive.
 */
class NamespaceDirective extends BaseDirective implements TypeManipulator, TypeExtensionManipulator
{
    public const NAME = 'namespace';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Redefine the default namespaces used in other directives.
The arguments are a map from directive names to namespaces.
"""
directive @namespace repeatable on FIELD_DEFINITION | OBJECT
GRAPHQL;
    }

    protected function addNamespacesToFields(ObjectTypeDefinitionNode|ObjectTypeExtensionNode &$objectType): void
    {
        $namespaceDirective = $this->directiveNode->cloneDeep();

        foreach ($objectType->fields as $fieldDefinition) {
            $existingNamespaces = ASTHelper::directiveDefinition($fieldDefinition, self::NAME);
            if ($existingNamespaces !== null) {
                $namespaceDirective->arguments = $namespaceDirective->arguments->merge($existingNamespaces->arguments);
            }

            $fieldDefinition->directives[] = $namespaceDirective;
        }
    }

    /** Apply manipulations from a type definition node. */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
            $this->addNamespacesToFields($typeDefinition);
        }
    }

    /** Apply manipulations from a type definition node. */
    public function manipulateTypeExtension(DocumentAST &$documentAST, TypeExtensionNode &$typeExtension): void
    {
        if ($typeExtension instanceof ObjectTypeExtensionNode) {
            $this->addNamespacesToFields($typeExtension);
        }
    }
}

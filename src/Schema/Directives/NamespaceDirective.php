<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\TypeExtensionNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;

/**
 * Redefine the default namespaces used in other directives.
 *
 * The args for this directive are a map from directive names to namespaces.
 * For example `@namespace(field: "App\\GraphQL")` applies the namespace
 * `App\GraphQL` to the `@field` directive.
 */
class NamespaceDirective extends BaseDirective implements TypeManipulator, TypeExtensionManipulator
{
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

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\ObjectTypeExtensionNode  $objectType
     * @return void
     */
    protected function addNamespacesToFields(&$objectType): void
    {
        $namespaceDirective = $this->directiveDefinition();

        foreach ($objectType->fields as $fieldDefinition) {
            if (
                $existingNamespaces = ASTHelper::directiveDefinition(
                    $fieldDefinition,
                    self::NAME
                )
            ) {
                $namespaceDirective->arguments = $namespaceDirective->arguments->merge($existingNamespaces->arguments);
            }

            $fieldDefinition->directives = $fieldDefinition->directives->merge([$namespaceDirective]);
        }
    }

    /**
     * Apply manipulations from a type definition node.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $typeDefinition
     * @return void
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
            $this->addNamespacesToFields($typeDefinition);
        }
    }

    /**
     * Apply manipulations from a type definition node.
     *
     * @param \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST
     * @param \GraphQL\Language\AST\TypeExtensionNode $typeExtension
     * @return void
     */
    public function manipulateTypeExtension(DocumentAST &$documentAST, TypeExtensionNode &$typeExtension): void
    {
        if ($typeExtension instanceof ObjectTypeExtensionNode) {
            $this->addNamespacesToFields($typeExtension);
        }
    }
}

<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class RuleFactory
{
    // NOTE: This will be replaced w/ a utility class.
    use HandlesDirectives;

    /**
     * Build rules for field.
     *
     * @param DirectiveNode                 $directive
     * @param InputValueDefinitionNode      $input
     * @param InputObjectTypeDefinitionNode $input
     * @param DocumentAST                   $document
     *
     * @return DocumentAST
     */
    public static function build(
        DirectiveNode $directive,
        InputValueDefinitionNode $field,
        InputObjectTypeDefinitionNode $input,
        DocumentAST $documentAST
    ) {
        // $instance = new static();
        // $documentAST = $instance->applyToMutation($directive, $input, $documentAST);
        // $documentAST = self::applyToInputTypes();

        return $documentAST;
    }

    /**
     * Apply rules to input types.
     */
    protected function applyToInputTypes()
    {
        $documentAST->inputTypes()
            ->each(function (InputObjectTypeDefinitionNode $inputType) use ($input) {
                collect($inputType->fields)->filter(function (InputValueDefinitionNode $field) use ($input, $inputType) {
                    return $field->type->name->value === $input->name->value;
                })->each(function ($field) use ($inputType) {
                    // Recursively walk up tree to find mutation field.
                    // dd($field->name->value, $inputType);
                });
            });
    }

    /**
     * Apply rules to mutation fields.
     *
     * @param DirectiveNode                 $directive
     * @param InputObjectTypeDefinitionNode $input
     * @param DocumentAST                   $documentAST
     *
     * @return DocumentAST
     */
    protected function applyToMutation(
        DirectiveNode $directive,
        InputObjectTypeDefinitionNode $input,
        DocumentAST $documentAST
    ) {
        $mutation = $documentAST->mutationType();

        $fields = collect($mutation->fields)
            ->map(function (FieldDefinitionNode $node) use ($directive, $input) {
                $arguments = collect($node->arguments)
                    ->filter(function (InputValueDefinitionNode $arg) use ($input) {
                        return $arg->type->name->value === $input->name->value;
                    })->map(function (InputValueDefinitionNode $arg) use ($directive) {
                        $currentPath = $this->directiveArgValue($directive, 'path', '');
                        $fullPath = $arg->name->value;

                        if (! empty($currentPath)) {
                            $fullPath = "{$fullPath}.{$currentPath}";
                        }

                        $inputType = PartialParser::inputObjectTypeDefinition("
                        input Dummy {
                            dummy: String @rules(path: \"{$fullPath}\")
                        }");

                        $path = $inputType->fields[0]->directives[0];
                        $directive->arguments = ASTHelper::mergeNodeList(
                            $directive->arguments, $path->arguments
                        );

                        $arg->directives = ASTHelper::mergeNodeList(
                            $arg->directives, NodeList::create([$directive])
                        );

                        return $arg;
                    })->toArray();

                $node->arguments = ASTHelper::mergeNodeList(
                    $node->arguments,
                    new NodeList($arguments)
                );

                return $node;
            })->toArray();

        $mutation->fields = ASTHelper::mergeNodeList(
            $mutation->fields,
            new NodeList($fields)
        );

        $documentAST->setDefinition($mutation);

        return $documentAST;
    }
}

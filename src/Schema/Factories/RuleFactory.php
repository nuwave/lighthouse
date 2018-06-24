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
        $instance = new static();
        $directiveClone = ASTHelper::cloneDefinition($directive);
        $directiveClone = $instance->appendPath($directiveClone, $field);

        $documentAST = $instance->applyToMutation($directiveClone, $input, $documentAST);
        $documentAST = $instance->applyToInputTypes($directiveClone, $input, $documentAST);

        return $documentAST;
    }

    /**
     * Apply rules to input types.
     *
     * @param DirectiveNode                 $directive
     * @param InputObjectTypeDefinitionNode $input
     * @param DocumentAST                   $documentAST
     *
     * @return DocumentAST
     */
    protected function applyToInputTypes(
        DirectiveNode $directive,
        InputObjectTypeDefinitionNode $input,
        DocumentAST $documentAST
    ) {
        $documentAST->inputTypes()
            ->each(function (InputObjectTypeDefinitionNode $inputType) use ($directive, $input, $documentAST) {
                collect($inputType->fields)->filter(function (InputValueDefinitionNode $field) use ($input) {
                    return $field->type->name->value === $input->name->value;
                })->each(function ($field) use ($directive, $inputType, $documentAST) {
                    $directiveClone = ASTHelper::cloneDefinition($directive);
                    $directiveClone = $this->appendPath($directiveClone, $field);

                    $documentAST = $this->applyToMutation($directiveClone, $inputType, $documentAST);
                    $documentAST = $this->applyToInputTypes($directiveClone, $inputType, $documentAST);
                });
            });

        return $documentAST;
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

        if (! $mutation) {
            return $documentAST;
        }

        $fields = collect($mutation->fields)
            ->map(function (FieldDefinitionNode $field) use ($directive, $input) {
                $arguments = collect($field->arguments)
                    ->map(function (InputValueDefinitionNode $arg) use ($directive, $input) {
                        if ($arg->type->name->value !== $input->name->value) {
                            return $arg;
                        }

                        $directiveClone = ASTHelper::cloneDefinition($directive);
                        $this->appendPath($directiveClone, $arg);

                        $arg->directives = ASTHelper::mergeNodeList(
                            $arg->directives, NodeList::create([$directiveClone])
                        );

                        return $arg;
                    })->toArray();

                $field->arguments = new NodeList($arguments);

                return $field;
            })->toArray();

        $mutation->fields = new NodeList($fields);

        // NOTE: This is currently not needed because we've already
        // mutated the fields on the original instance.
        // $documentAST->setDefinition($mutation);

        return $documentAST;
    }

    /**
     * Append current path to directive.
     *
     * @param DirectiveNode            $directive
     * @param InputValueDefinitionNode $arg
     *
     * @return DirectiveNode
     */
    protected function appendPath(DirectiveNode $directive, InputValueDefinitionNode $arg)
    {
        $currentPath = $this->directiveArgValue($directive, 'path', '');
        $fullPath = $arg->name->value;

        if (! empty($currentPath)) {
            $fullPath = "{$fullPath}.{$currentPath}";
        }

        $inputType = PartialParser::inputObjectTypeDefinition("
        input Dummy {
            dummy: String @rules(path: \"{$fullPath}\")
        }");

        $pathDirective = $inputType->fields[0]->directives[0];
        $directive->arguments = ASTHelper::mergeNodeList(
            new NodeList(collect($directive->arguments)->reject(function ($arg) {
                return 'path' === $arg->name->value;
            })->toArray()),
            $pathDirective->arguments
        );

        return $directive;
    }
}

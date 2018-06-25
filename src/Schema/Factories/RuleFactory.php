<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
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
     * @param InputValueDefinitionNode      $arg
     * @param InputObjectTypeDefinitionNode $inputType
     * @param DocumentAST                   $document
     *
     * @return DocumentAST
     */
    public static function build(
        DirectiveNode $directive,
        InputValueDefinitionNode $arg,
        InputObjectTypeDefinitionNode $inputType,
        DocumentAST $documentAST
    ) {
        $instance = new static();
        $directiveClone = ASTHelper::cloneDefinition($directive);
        $directiveClone = $instance->appendPath(
            $directiveClone,
            $arg,
            $instance->includesList($arg)
        );

        $documentAST = $instance->applyToMutation($directiveClone, $inputType, $documentAST);
        $documentAST = $instance->applyToInputTypes($directiveClone, $inputType, $documentAST);

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
                    return $this->unwrapArgType($field->type)->name->value === $input->name->value;
                })->each(function ($field) use ($directive, $inputType, $documentAST) {
                    $directiveClone = ASTHelper::cloneDefinition($directive);
                    $directiveClone = $this->appendPath($directiveClone, $field, $this->includesList($field));

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
                        $argType = $this->unwrapArgType($arg->type);

                        if ($argType->name->value !== $input->name->value) {
                            return $arg;
                        }

                        $directiveClone = ASTHelper::cloneDefinition($directive);
                        $this->appendPath($directiveClone, $arg, $this->includesList($arg), true);

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
     * @param bool                     $list
     *
     * @return DirectiveNode
     */
    protected function appendPath(
        DirectiveNode $directive,
        InputValueDefinitionNode $arg,
        $list,
        $final = false
    ) {
        $currentPath = $this->directiveArgValue($directive, 'path', '');
        $fullPath = $arg->name->value;

        if ($list) {
            $currentPath = "*.{$currentPath}";
        }

        if (! empty($currentPath)) {
            $fullPath = "{$fullPath}.{$currentPath}";
        }

        $pop = ['.', '*'];
        while ($final && ends_with($fullPath, $pop)) {
            $fullPath = substr_replace($fullPath, '', -1);
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

    /**
     * Unwrap input argument type.
     *
     * @param Node $arg
     *
     * @return Node
     */
    protected function unwrapArgType(Node $arg)
    {
        if (! data_get($arg, 'type')) {
            return $arg;
        } elseif (! data_get($arg, 'type.name')) {
            return $this->unwrapArgType($arg->type);
        }

        return $arg->type;
    }

    /**
     * Check if arg includes a list.
     *
     * @param Node $arg
     *
     * @return bool
     */
    protected function includesList(Node $arg)
    {
        $type = data_get($arg, 'type');

        if ($type instanceof ListTypeNode) {
            return true;
        } elseif (! is_null($type)) {
            return $this->includesList($type);
        }

        return false;
    }
}

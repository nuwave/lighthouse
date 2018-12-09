<?php

declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NonNullTypeNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\Args\RulesDirective;
use Nuwave\Lighthouse\Schema\Directives\Args\RulesForArrayDirective;

class RuleFactory
{
    /**
     * @var DocumentAST
     */
    protected $documentAST;
    /**
     * @var array
     */
    protected $rules = [];
    /**
     * @var array
     */
    protected $messages = [];

    /**
     * The constructor is protected, since this class is only supposed to be called
     * through the static "build" method.
     *
     * @param DocumentAST $documentAST
     */
    protected function __construct(DocumentAST $documentAST)
    {
        $this->documentAST = $documentAST;
    }

    /**
     * Build list of rules for field.
     *
     * @param string      $fieldName
     * @param string      $parentTypeName
     * @param array       $variables
     * @param DocumentAST $documentAST
     *
     * @return array [$rules, $messages]
     */
    public static function build(
        string $fieldName,
        string $parentTypeName,
        array $variables,
        DocumentAST $documentAST
    ): array {
        $instance = new static($documentAST);

        $parentDefinition = $documentAST->objectTypeDefinition($parentTypeName);

        $fieldDefinition = collect($parentDefinition->fields)
            ->first(function (FieldDefinitionNode $field) use ($fieldName) {
                return $fieldName === $field->name->value;
            });

        $instance->buildRulesRecursively($fieldDefinition, $variables);

        return [
            $instance->rules,
            $instance->messages,
        ];
    }

    /**
     * @param InputValueDefinitionNode $inputDefinition
     *
     * @throws DirectiveException
     *
     * @return array
     */
    public static function getRulesAndMessages(InputValueDefinitionNode $inputDefinition): array
    {
        $rulesDirective = ASTHelper::directiveDefinition($inputDefinition, RulesDirective::NAME);
        $arrayRulesDirective = ASTHelper::directiveDefinition($inputDefinition, RulesForArrayDirective::NAME);

        if (! $rulesDirective && ! $arrayRulesDirective) {
            return [[], []];
        }

        $typeIncludesList = self::includesList($inputDefinition);
        $rules = [];
        $messages = [];

        $inputDefinitionName = $inputDefinition->name->value;
        if ($arrayRulesDirective) {
            if (! $typeIncludesList) {
                throw new DirectiveException(
                    "The @arrayRules directive must only be defined on inputs that are lists, found on {$inputDefinitionName}"
                );
            }

            $rules[$inputDefinitionName] = self::getRulesFromDirective($arrayRulesDirective, $inputDefinitionName);
            $messages += self::getMessagesForDirective(
                $arrayRulesDirective,
                $inputDefinitionName
            );
        }

        if ($rulesDirective) {
            // We want those rules to get applied to the contents of the field,
            // so if the return type is a list, we utilize the Laravel array validation
            // by appending .* to the array
            $rulesPath = $typeIncludesList
                ? "{$inputDefinitionName}.*"
                : $inputDefinitionName;

            $rules[$rulesPath] = self::getRulesFromDirective($rulesDirective, $inputDefinitionName);
            $messages += self::getMessagesForDirective(
                $rulesDirective,
                $rulesPath
            );
        }

        return [$rules, $messages];
    }

    /**
     * @param FieldDefinitionNode|InputObjectTypeDefinitionNode $definition
     * @param array                                             $variables
     * @param string                                            $path
     *
     * @throws DefinitionException
     */
    protected function buildRulesRecursively($definition, array $variables, string $path = '')
    {
        self::collectInputDefinitions($definition)
            ->each(function (InputValueDefinitionNode $inputDefinition) use ($variables, $path) {
                list(
                    $rules,
                    $messages
                ) = self::getRulesAndMessages($inputDefinition);

                $this->rules += self::prependArrayKeysWithPath($rules, $path);
                $this->messages += self::prependArrayKeysWithPath($messages, $path);

                $inputDefinitionName = $inputDefinition->name->value;
                // The input is not given in the current variables
                // so we can stop generating further rules
                $variableForInput = array_get($variables, $inputDefinitionName);
                if (is_null($variableForInput)) {
                    return;
                }

                $typeName = ASTHelper::getUnderlyingTypeName($inputDefinition);

                // No further nesting is possible, so we can stop here
                if (! $inputObject = $this->documentAST->inputObjectTypeDefinition($typeName)) {
                    return;
                }

                // Prepend the given path unless we are still on the root level
                $nestedPath = '' === $path
                    ? $inputDefinitionName
                    : "{$path}.{$inputDefinitionName}";

                if (self::includesList($inputDefinition)) {
                    // At this point we have determined we are dealing with a list of nested input objects.
                    // We split into different subtrees, as the structure and depth of the input may differ
                    foreach ($variableForInput as $index => $values) {
                        $this->buildRulesRecursively($inputObject, $values, "{$nestedPath}.{$index}");
                    }
                } else {
                    // We know we have a single nested input object, so we just pass the
                    // corresponding slice of the variables and the previous path
                    $this->buildRulesRecursively($inputObject, $variableForInput, $nestedPath);
                }
            });
    }

    /**
     * Retrieve the InputDefinitions for either an InputObjectTypeDefinitionNode or a FieldDefinitionNode.
     *
     * @param Node $definition
     *
     * @throws DefinitionException
     *
     * @return Collection
     */
    protected static function collectInputDefinitions(Node $definition): Collection
    {
        if ($definition instanceof InputObjectTypeDefinitionNode) {
            return collect($definition->fields);
        }

        if ($definition instanceof FieldDefinitionNode) {
            return collect($definition->arguments);
        }

        throw new DefinitionException(
            "Definition {$definition->name->value} must be an InputObjectTypeDefinitionNode or a FieldDefinitionNode"
        );
    }

    /**
     * Check if the given InputValueDefinitionNode includes a list, e.g. [String].
     *
     * @param InputValueDefinitionNode $inputDefinition
     *
     * @return bool
     */
    protected static function includesList(InputValueDefinitionNode $inputDefinition): bool
    {
        $type = $inputDefinition->type;
        if ($type instanceof ListTypeNode) {
            return true;
        }

        // There might still be a list buried underneath
        if ($type instanceof NonNullTypeNode) {
            if ($type->type instanceof ListTypeNode) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param DirectiveNode $rulesDirective
     * @param string $inputDefinitionName
     *
     * @throws DirectiveException
     *
     * @return array
     */
    protected static function getRulesFromDirective(DirectiveNode $rulesDirective, string $inputDefinitionName): array
    {
        $rules = ASTHelper::directiveArgValue($rulesDirective, 'apply');

        if (0 === count($rules)) {
            throw new DirectiveException(
                "Must define at least one rule for on {$inputDefinitionName}"
            );
        }

        return $rules;
    }

    /**
     * @param DirectiveNode $rulesDirective
     * @param string        $forField
     *
     * @return array
     */
    protected static function getMessagesForDirective(DirectiveNode $rulesDirective, string $forField): array
    {
        $messages = collect(
            ASTHelper::directiveArgValue($rulesDirective, 'messages')
        )->mapWithKeys(
            function (string $message, string $forRule) use ($forField) {
                return [
                    "{$forField}.{$forRule}" => $message,
                ];
            }
        )->toArray();

        return $messages;
    }

    /**
     * @param array  $keyedArray
     * @param string $path
     *
     * @return array
     */
    protected static function prependArrayKeysWithPath(array $keyedArray, string $path): array
    {
        if ('' === $path) {
            return $keyedArray;
        }

        $result = [];
        foreach ($keyedArray as $key => $value) {
            $result["{$path}.{$key}"] = $value;
        }

        return $result;
    }
}

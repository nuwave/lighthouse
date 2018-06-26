<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class RuleFactory
{
    use HandlesDirectives;

    protected $resolved = [];

    /**
     * Build list of rules for field.
     *
     * @param DocumentAST $documentAST
     * @param array $variables
     * @param string $fieldName
     * @param string $parentType
     * @return array
     */
    public function build(DocumentAST $documentAST, array $variables, string $fieldName, string $parentType)
    {
        $rules = collect();

        $fieldDefinition = collect($documentAST->objectType($parentType)->fields)
            ->first(function (FieldDefinitionNode $fieldDefinition) use ($fieldName) {
                return $fieldName === $fieldDefinition->name->value;
            });

        collect(array_keys(array_dot($variables)))->sortByDesc(function ($key) {
            return strlen($key);
        })->each(function ($key) use ($documentAST, $fieldDefinition, &$rules) {
            $paths = collect(explode('.', $key))->reject(function ($key) {
                return is_numeric($key);
            })->values();

            while ($paths->isNotEmpty()) {
                $rules = $rules->merge($this->getRulesForPath(
                    $documentAST,
                    $fieldDefinition,
                    $paths->implode('.')
                ));

                $paths->pop();
            }
        });

        $mutationArgs = data_get($fieldDefinition, 'arguments');

        return $mutationArgs
            ? $rules->merge($this->getFieldRules($mutationArgs))->toArray()
            : $rules->toArray();
    }

    /**
     * Push resolved path.
     *
     * @param string $path
     *
     * @return array
     */
    protected function pushResolvedPath($path)
    {
        if (is_null($path)) {
            return $this->resolved;
        }

        $this->resolved = array_unique(array_merge($this->resolved, [$path]));

        return $this->resolved;
    }

    /**
     * Get nested validation rules.
     *
     * @param DocumentAST $documentAST
     * @param FieldDefinitionNode $mutationField
     * @param array $flatInput
     *
     * @return array
     */
    protected function getNestedRules(
        DocumentAST $documentAST,
        FieldDefinitionNode $mutationField,
        array $flatInput
    )
    {
        return collect($flatInput)
            ->flip()
            ->flatMap(function ($path) use ($documentAST, $mutationField) {
                return $this->getRulesForPath($documentAST, $mutationField, $path);
            })
            ->filter()
            ->toArray();
    }

    /**
     * Generate rules for nested input object.
     *
     * @param DocumentAST $documentAST
     * @param FieldDefinitionNode $field
     * @param string $path
     *
     * @return array|null
     */
    protected function getRulesForPath(DocumentAST $documentAST, FieldDefinitionNode $field, string $path)
    {
        $inputPath = explode('.', $path);
        array_pop($inputPath);
        $pathKey = implode('.', $inputPath);

        if (in_array($pathKey, $this->resolved)) {
            // We've already resolved this path so bail out.
            return null;
        }

        $resolvedPath = collect();

        /** @var InputValueDefinitionNode $input */
        $input = collect($inputPath)->reduce(function ($node, $path) use ($documentAST, $resolvedPath) {
            if (is_null($node)) {
                $resolvedPath->push($path);

                return null;
            }

            if ($this->includesList($node)) {
                $resolvedPath->push('*');
            }

            $resolvedPath->push($path);
            $arguments = null;

            if ($node instanceof InputObjectTypeDefinitionNode) {
                $arguments = $node->fields;
            } elseif ($node instanceof InputValueDefinitionNode) {
                $inputType = $documentAST->inputType($this->unwrapType($node->type)->name->value);
                $arguments = $inputType ? $inputType->fields : null;
            } elseif ($node instanceof FieldDefinitionNode) {
                $arguments = $node->arguments;
            } else {
                $node = $this->unwrapType($node);
                $arguments = data_get($node, 'arguments', data_get($node, 'fields'));
            }

            if (!$arguments) {
                return null;
            }

            return collect($arguments)->first(function ($arg) use ($path) {
                return $arg->name->value === $path;
            });
        }, $field);

        if (!$input) {
            return $this->getRulesForPath($documentAST, $field, $pathKey);
        }

        $list = $this->includesList($input);
        $type = $this->unwrapType($input);
        $inputType = $documentAST->inputType($type->name->value);

        return $inputType ? $this->getFieldRules($inputType->fields, $resolvedPath->implode('.'), $list) : null;
    }

    /**
     * Get rules for mutation field.
     *
     * @param NodeList $nodes
     * @param string|null $path
     * @param bool $list
     *
     * @return array
     */
    protected function getFieldRules(NodeList $nodes, string $path = null, bool $list = false): array
    {
        $rules = collect($nodes)->map(function (InputValueDefinitionNode $arg) use ($path, $list) {
            $directive = collect($arg->directives)->first(function (DirectiveNode $node) use ($path) {
                return 'rules' === $node->name->value;
            });

            if (!$directive) {
                return null;
            }

            $rules = $this->directiveArgValue($directive, 'apply', []);
            $path = $list && !empty($path) ? $path . '.*' : $path;
            $path = $path ? "{$path}.{$arg->name->value}" : $arg->name->value;

            return empty($rules) ? null : compact('path', 'rules');
        })->filter()->mapWithKeys(function ($ruleSet) {
            return [$ruleSet['path'] => $ruleSet['rules']];
        })->toArray();

        $this->pushResolvedPath($path);

        return $rules;
    }

    /**
     * Unwrap input argument type.
     *
     * @param Node $node
     *
     * @return Node
     */
    protected function unwrapType(Node $node): Node
    {
        if (!data_get($node, 'type')) {
            return $node;
        } elseif (!data_get($node, 'type.name')) {
            return $this->unwrapType($node->type);
        }

        return $node->type;
    }

    /**
     * Check if arg includes a list.
     *
     * @param Node $arg
     *
     * @return bool
     */
    protected function includesList(Node $arg): bool
    {
        $type = data_get($arg, 'type');

        if ($type instanceof ListTypeNode) {
            return true;
        } elseif (!is_null($type)) {
            return $this->includesList($type);
        }

        return false;
    }
}

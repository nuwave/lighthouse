<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class RuleFactory
{
    use HandlesDirectives;

    protected $nestedRules = [];

    /**
     * Build list of rules for field.
     *
     * @param array       $variables
     * @param ResolveInfo $info
     *
     * @return array
     */
    public function build(DocumentAST $documentAST, array $variables, $fieldName)
    {
        $flatInput = array_dot($variables);

        /** @var FieldDefinitionNode $field */
        $field = collect($documentAST->mutationType()->fields)
            ->first(function (FieldDefinitionNode $field) use ($fieldName) {
                return $field->name->value === $fieldName;
            });

        $rules = $this->mutationFieldRules($field);
        $nestedRules = collect($flatInput)->flip()->reject(function ($path) {
            return 1 === count(explode('.', $path));
        })->map(function ($path) use ($field) {
            return $this->mutationNestedRules($field, $path);
        })->filter()->mapWithKeys(function ($ruleSet) {
            return [$ruleSet['path'] => $ruleSet['rules']];
        });

        return array_merge($rules, $nestedRules);
    }

    /**
     * Get rules for mutation field.
     *
     * @param FieldDefinitionNode $field
     *
     * @return array
     */
    protected function mutationFieldRules(FieldDefinitionNode $field)
    {
        return collect($field->arguments)->map(function (InputValueDefinitionNode $arg) {
            $directive = collect($arg->directives)->first(function (DirectiveNode $node) {
                return 'rules' === $node->name->value;
            });

            if (! $directive) {
                return null;
            }

            $rules = $this->directiveArgValue($directive, 'apply', []);

            return empty($rules) ? null : ['path' => $arg->name->value, 'rules' => $rules];
        })->filter()->mapWithKeys(function ($ruleSet) {
            return [$ruleSet['path'] => $ruleSet['rules']];
        })->toArray();
    }

    /**
     * Generate rules for nested input object.
     *
     * @param DocumentAST         $documentAST
     * @param FieldDefinitionNode $field
     * @param string              $path
     *
     * @return array
     */
    protected function mutationNestedRules(DocumentAST $documentAST, FieldDefinitionNode $field, $path)
    {
        $inputPath = explode('.', $path);
        array_pop($inputPath);

        /** @var InputValueDefinitionNode $input */
        $input = collect($inputPath)->reduce(function (FieldDefinitionNode $field, $path) {
            if (is_null($field)) {
                return null;
            }

            return collect($field->arguments)->first(function ($arg) use ($path) {
                return $arg->name->value === $path;
            });
        }, $field);
    }
}

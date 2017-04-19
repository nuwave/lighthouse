<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\SelectionSet;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\Field as GraphQLField;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;

class FieldParser
{
    /**
     * The fragments.
     *
     * @var array
     */
    protected $fragments;

    /**
     * Prefetch data.
     *
     * @param  ResolveInfo $info
     * @param  int $depth
     * @return void
     */
    public function fetch(ResolveInfo $info, $depth = 999999)
    {
        $fields = [];

        /** @var GraphQLField $fieldAST */
        foreach ($info->fieldASTs as $fieldAST) {
            $fields = array_merge_recursive($fields, $this->foldSelectionSet($fieldAST->selectionSet, $depth));
        }

        return $fields;
    }

    /**
     * Fold field selection set.
     *
     * @param  SelectionSet $selectionSet
     * @param  int          $descend
     * @return array
     */
    protected function foldSelectionSet(SelectionSet $selectionSet, $descend)
    {
        $fields = [];

        foreach ($selectionSet->selections as $selectionAST) {
            if ($selectionAST instanceof GraphQLField) {
                $fields[$selectionAST->name->value] = $descend > 0 && ! empty($selectionAST->selectionSet)
                    ? $this->buildField($selectionAST, ['children' => $this->foldSelectionSet($selectionAST->selectionSet, $descend - 1)])
                    : $this->buildField($selectionAST);
            } elseif ($selectionAST instanceof FragmentSpread) {
                $spreadName = $selectionAST->name->value;
                if (isset($this->fragments[$spreadName])) {
                    /** @var FragmentDefinition $fragment */
                    $fragment = $this->fragments[$spreadName];
                    $fields += $this->foldSelectionSet($fragment->selectionSet, $descend);
                }
            }
        }

        return $fields;
    }

    /**
     * Build field output.
     *
     * @param  GraphQLField  $field
     * @param  array $data
     * @return array
     */
    protected function buildField(GraphQLField $field, $data = [])
    {
        $args = [];

        foreach ($field->arguments as $argument) {
            if ($argument->value instanceof Variable) {
                // TODO: Get rid of request helper
                $args[$argument->name->value] = array_get(request()->get('variables', []), $argument->name->value);
            } else {
                $args[$argument->name->value] = $argument->value->value;
            }
        }

        return array_merge([
            'parent' => isset($data['children']),
            'args' => $args,
        ], $data);
    }
}

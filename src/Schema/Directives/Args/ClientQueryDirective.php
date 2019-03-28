<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class ClientQueryDirective extends BaseDirective implements ArgBuilderDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'clientQuery';
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $whereConstraint
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $whereConstraint)
    {
        if($andConnectedConstraints = $whereConstraint['AND'] ?? null){
            $builder->whereNested(
                function($builder) use ($andConnectedConstraints): void {
                    foreach($andConnectedConstraints as $constraint){
                        $this->handleBuilder($builder, $constraint);
                    }
                },
                'and'
            );
        }

        if($orConnectedConstraints = $whereConstraint['OR'] ?? null){
            $builder->whereNested(
                function($builder) use ($orConnectedConstraints): void {
                    foreach($orConnectedConstraints as $constraint){
                        $this->handleBuilder($builder, $constraint);
                    }
                },
                'or'
            );
        }

        if($notConnectedConstraints = $whereConstraint['NOT'] ?? null){
            $builder->whereNested(
                function($builder) use ($notConnectedConstraints): void {
                    foreach($notConnectedConstraints as $constraint){
                        $this->handleBuilder($builder, $constraint);
                    }
                },
                'not'
            );
        }

        if($column = $whereConstraint['column'] ?? null){
            if(! $value = $whereConstraint['value']) {
                throw new Error(
                    "Did not receive a value to match the WhereConstraint for column {$column}."
                );
            }

            $builder->where(
                $column,
                $whereConstraint['operator'],
                $value
            );
        }

        return $builder;
    }
}

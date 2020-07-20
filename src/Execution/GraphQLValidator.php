<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GraphQLValidator extends Validator
{
    /**
     * Get the root object that was passed to the field that is being validated.
     *
     * @return mixed The root object.
     */
    public function getRoot()
    {
        return Arr::get($this->customAttributes, 'root');
    }

    /**
     * Get the context that was passed to the field that is being validated.
     */
    public function getContext(): GraphQLContext
    {
        return Arr::get($this->customAttributes, 'context');
    }

    /**
     * Get the resolve info that was passed to the field that is being validated.
     */
    public function getResolveInfo(): ResolveInfo
    {
        return Arr::get($this->customAttributes, 'resolveInfo');
    }

    /**
     * Return the dot separated path of the field that is being validated.
     */
    public function getFieldPath(): string
    {
        return implode(
            '.',
            $this->getResolveInfo()->path
        );
    }
}

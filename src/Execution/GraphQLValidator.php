<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GraphQLValidator extends Validator
{
    /**
     * @return mixed
     */
    public function getRoot()
    {
        return Arr::get($this->customAttributes, 'root');
    }

    /**
     * @return GraphQLContext
     */
    public function getContext()
    {
        return Arr::get($this->customAttributes, 'context');
    }

    /**
     * @return ResolveInfo
     */
    public function getResolveInfo(): ResolveInfo
    {
        return Arr::get($this->customAttributes, 'resolveInfo');
    }

    /**
     * Return the dot separated path of the field that is being validated.
     *
     * @return string
     */
    public function getFieldPath(): string
    {
        return implode(
            '.',
            $this->getResolveInfo()->path
        );
    }
}

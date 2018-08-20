<?php

namespace Nuwave\Lighthouse\Support\Validator;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Validation\Validator;
use Nuwave\Lighthouse\Schema\Context;

class GraphQLValidator extends Validator
{
    /**
     * Get root object.
     *
     * @return mixed
     */
    public function getRoot()
    {
        return array_get($this->customAttributes, 'root');
    }

    /**
     * Get context object.
     *
     * @return Context
     */
    public function getContext(): Context
    {
        return array_get($this->customAttributes, 'context');
    }

    /**
     * Get field resolve info.
     *
     * @return ResolveInfo
     */
    public function getResolveInfo(): ResolveInfo
    {
        return array_get($this->customAttributes, 'resolveInfo');
    }
}

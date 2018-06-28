<?php

namespace Nuwave\Lighthouse\Support\Validator;

use Illuminate\Validation\Validator;

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
     * @return \Nuwave\Lighthouse\Schema\Context
     */
    public function getContext()
    {
        return array_get($this->customAttributes, 'context');
    }

    /**
     * Get field resolve info.
     *
     * @return \GraphQL\Type\Definition\ResolveInfo
     */
    public function getResolveInfo()
    {
        return array_get($this->customAttributes, 'resolveInfo');
    }
}

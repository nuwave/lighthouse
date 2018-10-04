<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Validation\Validator;
use Nuwave\Lighthouse\Schema\Context;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\ValidationException;

class GraphQLValidator extends Validator
{
    /**
     * Run the validator's rules against its data.
     *
     * @throws ValidationException
     *
     * @return array
     */
    public function validate()
    {
        if ($this->fails()) {
            throw new ValidationException($this);
        }

        $data = collect($this->getData());

        return $data->only(collect($this->getRules())->keys()->map(function ($rule) {
            return explode('.', $rule)[0];
        })->unique())->toArray();
    }

    /**
     * @return mixed
     */
    public function getRoot()
    {
        return array_get($this->customAttributes, 'root');
    }

    /**
     * @return Context
     */
    public function getContext()
    {
        return array_get($this->customAttributes, 'context');
    }

    /**
     * @return ResolveInfo
     */
    public function getResolveInfo(): ResolveInfo
    {
        return array_get($this->customAttributes, 'resolveInfo');
    }

    public function getFieldPath(): string
    {
        return implode('.', $this->getResolveInfo()->path);
    }
}

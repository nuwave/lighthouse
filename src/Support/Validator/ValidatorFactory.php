<?php

namespace Nuwave\Lighthouse\Support\Validator;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Validation\Validator;

class ValidatorFactory
{
    /**
     * Resolve a new Validator instance.
     *
     * @param \Illuminate\Contracts\Translation\Translator $translator
     * @param array                                        $data
     * @param array                                        $rules
     * @param array                                        $messages
     * @param array                                        $customAttributes
     *
     * @return \Illuminate\Validation\Validator
     */
    public static function resolve(
        $translator,
        array $data,
        array $rules,
        array $messages,
        array $customAttributes
    ) {
        $resolveInfo = array_get($customAttributes, 'resolveInfo');

        return $resolveInfo instanceof ResolveInfo
            ? new GraphQLValidator($translator, $data, $rules, $messages, $customAttributes)
            : new Validator($translator, $data, $rules, $messages, $customAttributes);
    }

    /**
     * Validate the value is present on a mutation field.
     *
     * @param string           $attribute
     * @param mixed            $value
     * @param array            $parameters
     * @param GraphQLValidator $validator
     *
     * @return bool
     */
    public function requiredWithMutation($attribute, $value, $parameters, $validator)
    {
        $info = $validator->getResolveInfo();

        if ('Mutation' !== data_get($info, 'parentType.name')) {
            return true;
        }

        if (in_array($info->fieldName, $parameters)) {
            return ! empty($value);
        }

        return true;
    }
}

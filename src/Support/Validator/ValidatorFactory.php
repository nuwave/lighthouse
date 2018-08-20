<?php

namespace Nuwave\Lighthouse\Support\Validator;

use Illuminate\Validation\Validator;
use GraphQL\Type\Definition\ResolveInfo;

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
     * @return Validator
     */
    public static function resolve(
        $translator,
        array $data,
        array $rules,
        array $messages,
        array $customAttributes
    ): Validator {
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
    public function requiredWithMutation(string $attribute, $value, array $parameters, GraphQLValidator $validator): bool
    {
        $info = $validator->getResolveInfo();

        if ('Mutation' !== data_get($info, 'parentType.name')) {
            return true;
        }

        if (in_array($info->fieldName, $parameters)) {
            return ! is_null($value);
        }

        return true;
    }
}

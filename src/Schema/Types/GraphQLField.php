<?php

namespace Nuwave\Lighthouse\Schema\Types;

use Nuwave\Lighthouse\Support\Exceptions\ValidationError;

class GraphQLField
{
    /**
     * Field attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Create new instance of graphql field.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Arguments this field accepts.
     *
     * @return array
     */
    public function args()
    {
        return array_get($this->attributes, 'args', []);
    }

    /**
     * The field type.
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public function type()
    {
        return array_get($this->attributes, 'type');
    }

    /**
     * Rules to apply to field.
     *
     * @return array
     */
    public function rules()
    {
        return array_get($this->attributes, 'rules', []);
    }

    /**
     * Get the attributes of the field.
     *
     * @return array
     */
    public function attributes()
    {
        $attributes['args'] = $this->args();
        $attributes['type'] = $this->type();
        $attributes['resolve'] = $this->getResolver();

        return $attributes;
    }

    /**
     * Get rules for field.
     *
     * @return array
     */
    public function getRules()
    {
        $arguments = func_get_args();
        $args = $this->args();

        return collect($args)->map(function ($arg, $name) use ($arguments) {
            $rules = data_get($arg, 'rules');

            if (! $rules) {
                return;
            }

            return is_callable($rules)
                ? call_user_func_array($arg['rules'], $arguments)
                : $rules;
        })
        ->merge(call_user_func_array([$this, 'rules'], $arguments))
        ->filter()
        ->toArray();
    }

    /**
     * Get the field resolver.
     *
     * @return \Closure|null
     */
    protected function getResolver()
    {
        if (! array_has($this->attributes, 'resolve')
            && ! array_has($this->attributes, 'relayResolve')
        ) {
            return;
        }

        $resolver = array_get($this->attributes, 'resolve', array_get($this->attributes, 'relayResolve'));

        return function () use ($resolver) {
            $arguments = func_get_args();
            $rules = call_user_func_array([$this, 'getRules'], $arguments);

            if (sizeof($rules)) {
                $input = $this->getInput($arguments);
                $validator = validator($input, $rules);
                if ($validator->fails()) {
                    throw with(new ValidationError('validation'))->setValidator($validator);
                }
            }

            return call_user_func_array($resolver, $arguments);
        };
    }

    /**
     * Get input for validation.
     *
     * @param array $arguments
     *
     * @return array
     */
    protected function getInput(array $arguments)
    {
        return array_get($arguments, 1, []);
    }

    /**
     * Convert the field instance to an array.
     *
     * @param array $attributes
     *
     * @return array
     */
    public static function toArray(array $attributes = [])
    {
        return (new static($attributes))->attributes();
    }
}

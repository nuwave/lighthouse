<?php
namespace Nuwave\Lighthouse\Support\Definition;

use Illuminate\Support\Fluent;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;

class GraphQLField extends Fluent
{
    use GlobalIdTrait;

    /**
     * Arguments this field accepts.
     *
     * @return array
     */
    public function args()
    {
        return [];
    }

    /**
     * Field attributes.
     *
     * @return array
     */
    public function attributes()
    {
        return [];
    }

    /**
     * The field type.
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public function type()
    {
        return null;
    }

    /**
     * Rules to apply to field.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * Get the attributes of the field.
     *
     * @return array
     */
    public function getAttributes()
    {
        $attributes = array_merge($this->attributes, [
            'args' => $this->args()
        ], $this->attributes());

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

        return collect($args)
            ->transform(function ($arg, $name) use ($arguments) {
                if (isset($arg['rules'])) {
                    if (is_callable($arg['rules'])) {
                        return call_user_func_array($arg['rules'], $arguments);
                    }
                    return $arg['rules'];
                }
                return null;
            })
            ->merge(call_user_func_array([$this, 'rules'], $arguments))
            ->reject(function ($arg) {
                return is_null($arg);
            })
            ->toArray();
    }
    /**
     * Get the field resolver.
     *
     * @return \Closure|null
     */
    protected function getResolver()
    {
        if (!method_exists($this, 'resolve') && !method_exists($this, 'relayResolve')) {
            return null;
        }

        $resolver = method_exists($this, 'resolve') ? array($this, 'resolve') : array($this, 'relayResolve');

        return function () use ($resolver) {
            $arguments = func_get_args();
            $rules = call_user_func_array([$this, 'getRules'], $arguments);

            if (sizeof($rules)) {
                $input = $this->getInput($arguments);
                $validator = app('validator')->make($input, $rules);
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
     * @param  array  $arguments
     * @return array
     */
    protected function getInput(array $arguments)
    {
        return array_get($arguments, 1, []);
    }

    /**
     * Convert the Fluent instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getAttributes();
    }

    /**
     * Transform into field array.
     *
     * @return array
     */
    public static function field()
    {
        return (new static)->toArray();
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $attributes = $this->getAttributes();

        return isset($attributes[$key]) ? $attributes[$key]:null;
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->getAttributes()[$key]);
    }
}

<?php

namespace Nuwave\Lighthouse\Schema\Types;

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
     * Get field description.
     *
     * @return string
     */
    public function description()
    {
        return array_get($this->attributes, 'description');
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
     * Get field complexity closure.
     *
     * @return \Closure|null
     */
    public function complexity()
    {
        return array_get($this->attributes, 'complexity');
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
        $attributes['description'] = $this->description();

        if ($complexity = $this->complexity()) {
            $attributes['complexity'] = $complexity;
        }

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

    public function getValidationMessages()
    {
        $arguments = func_get_args();
        $args = $this->args();

        return collect($args)->map(function ($arg, $name) use ($arguments) {
            $messages = data_get($arg, 'messages');

            if (! $messages) {
                return;
            }

            return $messages;
            })
            ->filter()
            ->flatMap(function ($data) {
                return $data;
            })
            ->toArray();
    }

    /**
     * Get the field resolver.
     *
     * @return array|void
     */
    protected function getResolver()
    {
        if (! array_has($this->attributes, 'resolve')) {
            return;
        }

        return [$this, 'resolve'];
    }

    /**
     * Resolve field.
     *
     * @return mixed
     */
    public function resolve()
    {
        $arguments = func_get_args();

        if (isset($arguments[1])) {
            $arguments[1] = $this->resolveArgs($arguments[1]);
        }

        $rules = $this->getRules(...$arguments);
        $messages = $this->getValidationMessages(...$arguments);

        if (sizeof($rules)) {
            $input = $this->getInput($arguments);
            $validator = validator($input, $rules, $messages);
            $validator->validate();
        }

        return call_user_func_array(
            array_get($this->attributes, 'resolve'),
            $arguments
        );
    }

    /**
     * Resolve argument(s).
     *
     * @param array $args
     *
     * @return array
     */
    protected function resolveArgs(array $args)
    {
        $resolvers = collect($this->args())->filter(function ($arg) {
            return array_has($arg, 'resolve');
        });

        if ($resolvers->isEmpty()) {
            return $args;
        }

        return collect($args)->map(function ($arg, $key) use ($resolvers) {
            return $resolvers->has($key)
                ? $resolvers->get($key)['resolve']($arg)
                : $arg;
        })->toArray();
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

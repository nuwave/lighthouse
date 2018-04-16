<?php

namespace Nuwave\Lighthouse\Support\Validator;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;

abstract class Validator
{
    /**
     * Root field element.
     *
     * @var mixed
     */
    protected $root;

    /**
     * Field arguments.
     *
     * @var array
     */
    protected $args;

    /**
     * GraphQL Context.
     *
     * @var \Nuwave\Lighthouse\Schema\Context
     */
    protected $context;

    /**
     * Field resolve info.
     *
     * @var \GraphQL\Type\Definition\ResolveInfo
     */
    protected $info;

    /**
     * Create new instance of field validator.
     *
     * @param mixed                                $root
     * @param array                                $args
     * @param \Nuwave\Lighthouse\Schema\Context    $context
     * @param \GraphQL\Type\Definition\ResolveInfo $info
     */
    public function __construct($root, array $args, $context, $info)
    {
        $this->root = $root;
        $this->args = $args;
        $this->context = $context;
        $this->info = $info;
    }

    /**
     * Process validator for field.
     *
     * @return bool
     */
    public function validate()
    {
        if (! $this->can()) {
            $this->handleUnauthorized();
        }

        $validator = validator(
            $this->args(),
            $this->rules(),
            $this->messages()
        );

        if ($validator->fails()) {
            $this->handleInvalid($validator);
        }

        return true;
    }

    /**
     * Get field argument.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function argument($key, $default = null)
    {
        return array_get($this->args, $key, $default);
    }

    /**
     * Get field arguments.
     *
     * @return array
     */
    protected function args()
    {
        return $this->args;
    }

    /**
     * Get input (or input argument).
     *
     * @param string|null $key
     * @param mixed|null  $default
     *
     * @return \Illuminate\Support\Collection|mixed
     */
    protected function input($key = null, $default = null)
    {
        return $key
            ? array_get($this->args, $key, $default)
            : collect($this->args);
    }

    /**
     * Get validator messages.
     *
     * @return array
     */
    protected function messages()
    {
        return [];
    }

    /**
     * Check if user is authorized.
     *
     * @return bool
     */
    protected function can()
    {
        return true;
    }

    /**
     * Handle an unauthorized request.
     */
    protected function handleUnauthorized()
    {
        throw new Error('Unauthorized');
    }

    /**
     * Handle an invalid request.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     */
    protected function handleInvalid($validator)
    {
        throw with(new ValidationError('validation'))->setValidator($validator);
    }

    /**
     * Get rules for field.
     *
     * @return array
     */
    abstract protected function rules();
}

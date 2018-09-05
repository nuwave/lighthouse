<?php

namespace Nuwave\Lighthouse\Support\Validator;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Nuwave\Lighthouse\Schema\Context;
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
     * @var Context
     */
    protected $context;

    /**
     * Field resolve info.
     *
     * @var ResolveInfo
     */
    protected $info;

    /**
     * Create new instance of field validator.
     *
     * @param mixed $root
     * @param array $args
     * @param Context $context
     * @param ResolveInfo $info
     */
    public function __construct($root, array $args, $context, ResolveInfo $info)
    {
        $this->root = $root;
        $this->args = $args;
        $this->context = $context;
        $this->info = $info;
    }

    /**
     * Process validator for field.
     *
     * @throws Error
     *
     * @return bool
     */
    public function validate(): bool
    {
        if (!$this->can()) {
            $this->handleUnauthorized();
        }

        $validator = validator(
            $this->args,
            $this->rules(),
            $this->messages()
        );

        if ($validator->fails()) {
            $this->handleInvalid($validator);
        }

        return true;
    }

    /**
     * Check if user is authorized.
     *
     * @return bool
     */
    protected function can(): bool
    {
        return true;
    }

    /**
     * Handle an unauthorized request.
     * @throws Error
     */
    protected function handleUnauthorized()
    {
        throw new Error('Unauthorized');
    }

    /**
     * Get rules for field.
     *
     * @return array
     */
    abstract protected function rules(): array;

    /**
     * Get validator messages.
     *
     * @return array
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Handle an invalid request.
     *
     * @param ValidatorContract $validator
     */
    protected function handleInvalid(ValidatorContract $validator)
    {
        throw with(new ValidationError('validation'))->setValidator($validator);
    }

    /**
     * Get field argument.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    protected function argument(string $key, $default = null)
    {
        return array_get($this->args, $key, $default);
    }

    /**
     * Get input (or input argument).
     *
     * @param string|null $key
     * @param mixed|null $default
     *
     * @return \Illuminate\Support\Collection|mixed
     */
    protected function input($key = null, $default = null)
    {
        return $key
            ? array_get($this->args, $key, $default)
            : collect($this->args);
    }
}

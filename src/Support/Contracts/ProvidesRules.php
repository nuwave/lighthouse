<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Provide rules and custom messages for field validation.
 *
 * https://laravel.com/docs/validation
 */
interface ProvidesRules
{
    /**
     * Return validation rules for the arguments.
     */
    public function rules(): array;

    /**
     * Return custom messages for the rules.
     */
    public function messages(): array;
}

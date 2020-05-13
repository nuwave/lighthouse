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
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Return custom messages for the rules.
     *
     * @return array<string, mixed>
     */
    public function messages(): array;
}

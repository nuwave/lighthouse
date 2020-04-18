<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ProvidesRules
{
    /**
     * Return validation rules for multiple arguments.
     *
     * array<string, mixed>
     */
    public function rules(): array;

    /**
     * Return custom messages for the rules.
     *
     * array<string, mixed>
     */
    public function messages(): array;
}

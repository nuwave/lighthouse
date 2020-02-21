<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ProvidesRule
{
    /**
     * Return validation rules for a single argument.
     *
     * @return array|string
     */
    public function rule();

    /**
     * Return custom messages for the rules.
     *
     * @return array
     */
    public function messages(): array;
}

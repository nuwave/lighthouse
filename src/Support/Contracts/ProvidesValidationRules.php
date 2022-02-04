<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ProvidesValidationRules
{
    /**
     * A set of rules for query validation step.
     *
     * Returning `null` enables all available rules.
     * Empty array skips query validation entirely.
     *
     * @return array<string, \GraphQL\Validator\Rules\ValidationRule>|null
     */
    public function validationRules(): ?array;
}

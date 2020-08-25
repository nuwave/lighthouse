<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ProvidesValidationRules
{
    /**
     * A set of rules for query validation step.
     *
     * Returning `null` enables all available rules.
     * Empty array would allow to skip query validation.
     *
     * @return array<class-string<\GraphQL\Validator\Rules\ValidationRule>, \GraphQL\Validator\Rules\ValidationRule>|null
     */
    public function validationRules(): ?array;
}

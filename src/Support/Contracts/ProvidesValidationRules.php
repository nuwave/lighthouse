<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

interface ProvidesValidationRules
{
    /**
     * Rules to use for query validation.
     *
     * Returning `null` enables all available rules,
     * returning `[]` skips query validation entirely.
     *
     * @return array<string, \GraphQL\Validator\Rules\ValidationRule>|null
     */
    public function validationRules(): ?array;
}

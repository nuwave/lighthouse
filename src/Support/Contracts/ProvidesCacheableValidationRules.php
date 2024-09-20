<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

interface ProvidesCacheableValidationRules extends ProvidesValidationRules
{
    /**
     * A set of rules for the first query validation step.
     *
     * These rules are executed first and their result is cached.
     *
     * @return array<string, \GraphQL\Validator\Rules\ValidationRule>
     */
    public function cacheableValidationRules(): array;

    /**
     * A set of rules for the second query validation step.
     *
     * These rules are always executed and not cached.
     *
     * Returning `null` enables all available rules.
     * Empty array skips query validation entirely.
     *
     * @return array<string, \GraphQL\Validator\Rules\ValidationRule>|null
     */
    public function validationRules(): ?array;
}

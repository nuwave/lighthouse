<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

/** Allows splitting validation into a cacheable first step and a non-cacheable second step. */
interface ProvidesCacheableValidationRules extends ProvidesValidationRules
{
    /**
     * Rules where the result depends only on the schema and the query string.
     *
     * These rules are executed before non-cacheable rules and may not run
     * at all when their result is already cached.
     *
     * @return array<string, \GraphQL\Validator\Rules\ValidationRule>
     */
    public function cacheableValidationRules(): array;

    /**
     * Rules where the result also depends on variables or other data.
     *
     * These rules are always executed and their result is never cached.
     *
     * Returning `null` enables all available rules,
     * returning `[]` skips query validation entirely.
     *
     * @return array<string, \GraphQL\Validator\Rules\ValidationRule>|null
     */
    public function validationRules(): ?array;
}

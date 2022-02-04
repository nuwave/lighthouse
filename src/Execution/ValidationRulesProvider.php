<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules;

class ValidationRulesProvider implements ProvidesValidationRules
{
    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $configRepository;

    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    public function validationRules(): ?array
    {
        // @phpstan-ignore-next-line remove when graphql-php 15 has an accurate type for DocumentValidator::allRules()
        return [
            QueryComplexity::class => new QueryComplexity($this->configRepository->get('lighthouse.security.max_query_complexity', 0)),
            QueryDepth::class => new QueryDepth($this->configRepository->get('lighthouse.security.max_query_depth', 0)),
            DisableIntrospection::class => new DisableIntrospection($this->configRepository->get('lighthouse.security.disable_introspection', 0)),
        ] + DocumentValidator::allRules();
    }
}

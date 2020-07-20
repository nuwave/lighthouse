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
    protected $config;

    public function __construct(ConfigRepository $config)
    {
        $this->config = $config;
    }

    public function validationRules(): ?array
    {
        return [
            QueryComplexity::class => new QueryComplexity($this->config->get('lighthouse.security.max_query_complexity', 0)),
            QueryDepth::class => new QueryDepth($this->config->get('lighthouse.security.max_query_depth', 0)),
            DisableIntrospection::class => new DisableIntrospection($this->config->get('lighthouse.security.disable_introspection', false)),
        ] + DocumentValidator::defaultRules();
    }
}

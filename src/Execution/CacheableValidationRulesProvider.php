<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Support\Contracts\ProvidesCacheableValidationRules;

class CacheableValidationRulesProvider implements ProvidesCacheableValidationRules
{
    public function __construct(
        protected ConfigRepository $configRepository,
    ) {}

    public function cacheableValidationRules(): array
    {
        $result = [
            QueryDepth::class => new QueryDepth($this->configRepository->get('lighthouse.security.max_query_depth', 0)),
            DisableIntrospection::class => new DisableIntrospection($this->configRepository->get('lighthouse.security.disable_introspection', 0)),
        ] + DocumentValidator::allRules();

        unset($result[QueryComplexity::class]);

        return $result;
    }

    public function validationRules(): ?array
    {
        $maxQueryComplexity = $this->configRepository->get('lighthouse.security.max_query_complexity', 0);

        return $maxQueryComplexity === 0
            ? []
            : [
                QueryComplexity::class => new QueryComplexity($maxQueryComplexity),
            ];
    }
}

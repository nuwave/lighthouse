<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives\Fixtures;

use Tests\Utils\Models\Company;

final class CompanyWasCreatedEvent
{
    public function __construct(
        public Company $company,
    ) {}
}

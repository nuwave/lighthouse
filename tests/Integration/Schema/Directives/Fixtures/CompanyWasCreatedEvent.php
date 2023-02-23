<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives\Fixtures;

use Tests\Utils\Models\Company;

final class CompanyWasCreatedEvent
{
    /**
     * @var \Tests\Utils\Models\Company
     */
    public $company;

    public function __construct(Company $company)
    {
        $this->company = $company;
    }
}

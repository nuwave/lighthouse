<?php

namespace Tests\Integration\Schema\Directives\Fields\Fixtures;

use Tests\Utils\Models\Company;

class CompanyWasCreatedEvent
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

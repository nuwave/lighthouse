<?php

namespace Tests\Integration\Schema\Directives\Fixtures;

use Tests\Utils\Models\Company;

class CompanyWasCreatedEvent
{
    /**
     * @var \Tests\Utils\Models\Company
     */
    public $company;

    /**
     * CompanyWasCreatedEvent constructor.
     *
     * @param  \Tests\Utils\Models\Company  $company
     * @return void
     */
    public function __construct(Company $company)
    {
        $this->company = $company;
    }
}

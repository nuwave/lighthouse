<?php

namespace Tests\Unit\Testing;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;

class MakesGraphQLRequestsTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.route.prefix', 'prefix');
    }

    public function testGraphQLEndpointUrlWithPrefix(): void
    {
        $this->assertSame(
            'http://localhost/prefix/graphql',
            $this->graphQLEndpointUrl()
        );
    }
}

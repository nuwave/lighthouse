<?php

namespace Tests\Unit\Testing;

use Tests\TestCase;

class MakesGraphQLRequestsTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];
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

<?php

namespace Tests\Integration\Schema\Extensions;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Company;
use Nuwave\Lighthouse\Support\Http\Responses\Memory;
use Nuwave\Lighthouse\Schema\Extensions\DeferExtension;
use Nuwave\Lighthouse\Support\Http\Responses\CanSendResponse;

class DeferExtensionDBTest extends DBTestCase
{
    /** @var \Closure */
    protected static $resolver;

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->stream = new Memory();

        $app->singleton(CanSendResponse::class, function () {
            return $this->stream;
        });

        $app['config']->set('lighthouse.extensions', [DeferExtension::class]);
        $app['config']->set('app.debug', true);
    }

    /**
     * @test
     */
    public function itCanDeferBelongsToFields()
    {
        $queries = 0;
        $resolver = addslashes(self::class).'@resolve';
        $company = factory(Company::class)->create();
        $user = factory(User::class)->create([
            'company_id' => $company->getKey(),
        ]);

        self::$resolver = function () use ($user) {
            return $user;
        };

        $this->schema = "
        type Company {
            name: String!
        }

        type User {
            email: String!
            company: Company
        }

        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }";

        $query = '
        {
            user {
                email
                company @defer {
                    name
                }
            }
        }';

        \DB::listen(function ($q) use (&$queries) {
            ++$queries;
        });

        $this->postJson('/graphql', compact('query'))
            ->baseResponse
            ->send();

        $chunks = $this->stream->chunks;
        $this->assertEquals(1, $queries);
        $this->assertCount(2, $chunks);

        $deferredUser = $chunks[0];
        $this->assertEquals($user->email, array_get($deferredUser, 'data.user.email'));
        $this->assertNull(array_get($deferredUser, 'data.user.company'));

        $deferredCompany = $chunks[1];
        $this->assertArrayHasKey('user.company', $deferredCompany);
        $this->assertEquals($company->name, array_get($deferredCompany['user.company'], 'name'));
    }

    /**
     * @test
     */
    public function itCanDeferNestedRelationshipFields()
    {
        $queries = 0;
        $resolver = addslashes(self::class).'@resolve';
        $company = factory(Company::class)->create();
        $users = factory(User::class, 5)->create([
            'company_id' => $company->getKey(),
        ]);
        $user = $users[0];

        self::$resolver = function () use ($user) {
            return $user;
        };

        $this->schema = "
        type Company {
            name: String!
            users: [User] @hasMany
        }

        type User {
            email: String!
            company: Company
        }

        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }";

        $query = '
        {
            user {
                email
                company @defer {
                    name
                    users @defer {
                        email
                    }
                }
            }
        }';

        \DB::listen(function ($q) use (&$queries) {
            ++$queries;
        });

        $this->postJson('/graphql', compact('query'))
            ->baseResponse
            ->send();

        $chunks = $this->stream->chunks;
        $this->assertEquals(2, $queries);
        $this->assertCount(3, $chunks);

        $deferredUser = $chunks[0];
        $this->assertEquals($user->email, array_get($deferredUser, 'data.user.email'));
        $this->assertNull(array_get($deferredUser, 'data.user.company'));

        $deferredCompany = $chunks[1];
        $this->assertArrayHasKey('user.company', $deferredCompany);
        $this->assertEquals($company->name, array_get($deferredCompany['user.company'], 'name'));
        $this->assertNull(array_get($deferredCompany['user.company'], 'users'));

        $deferredUsers = $chunks[2];
        $this->assertArrayHasKey('user.company.users', $deferredUsers);
        $this->assertCount(5, $deferredUsers['user.company.users']);
        $this->assertEquals(
            $users->map(function ($user) {
                return ['email' => $user->email];
            })->values()->toArray(),
            $deferredUsers['user.company.users']
        );
    }

    public function resolve()
    {
        $resolver = self::$resolver;

        return $resolver();
    }
}

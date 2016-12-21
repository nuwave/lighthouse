<?php

namespace Nuwave\Lighthouse\Tests\DataLoader;

use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Tests\Support\Models\Task;
use Nuwave\Lighthouse\Tests\Support\Models\Company;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\TaskType;

class DataLoaderTest extends DBTestCase
{
    use GlobalIdTrait;

    /**
     * Company model.
     *
     * @var Company
     */
    protected $company;

    /**
     * Collection of generated users.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $users;

    /**
     * Set up test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->company = factory(Company::class)->create();

        $this->users = factory(User::class, 5)->create([
            'company_id' => $this->company->id
        ]);

        $this->users->each(function ($user) {
            factory(Task::class, 5)->create([
                'user_id' => $user->id
            ]);
        });
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('lighthouse.schema.register', function () {
            $graphql = app('graphql');
            $graphql->schema()->type('company', Support\CompanyLoaderType::class);
            $graphql->schema()->type('user', Support\UserLoaderType::class);
            $graphql->schema()->type('task', TaskType::class);
            $graphql->schema()->query('companyQuery', Support\CompanyLoaderQuery::class);
            $graphql->schema()->dataLoader('task', Support\UserTaskDataLoader::class);
        });
    }

    /**
     * @test
     */
    public function itCanResolveNestedData()
    {
        $queries = 0;
        $expectedQueries = 4; // Extra query is run due to pagination count.

        \DB::listen(function ($query) use (&$queries) {
            $queries++;
        });

        $query = $this->executeQuery($this->getQuery());
        $data = array_get($query, 'data.companyQuery');

        $this->assertEquals($expectedQueries, $queries, "Expected $expectedQueries queries to run but {$queries} ran.");
        $this->assertCount(5, array_get($data, 'users.edges'));
        $this->assertCount(2, array_get($data, 'users.edges.0.node.tasks.edges', []));
        $this->assertCount(2, array_get($data, 'users.edges.1.node.tasks.edges', []));
        $this->assertCount(2, array_get($data, 'users.edges.2.node.tasks.edges', []));
        $this->assertCount(2, array_get($data, 'users.edges.3.node.tasks.edges', []));
        $this->assertCount(2, array_get($data, 'users.edges.4.node.tasks.edges', []));
    }

    /**
     * Get query.
     *
     * @return string
     */
    protected function getQuery()
    {
        $id = $this->encodeGlobalId(CompanyType::class, $this->company->id);

        return '{
            companyQuery(id: "'.$id.'") {
                name
                users(first:5) {
                    edges {
                        node {
                            name
                            email
                            tasks(first:2) {
                                edges {
                                    node {
                                        title
                                        description
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }';
    }
}

<?php

namespace Nuwave\Lighthouse\Tests\DataLoader;

use Nuwave\Lighthouse\Tests\DataLoader\Support\UserFetcherType;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Tests\Support\Models\Task;
use Nuwave\Lighthouse\Tests\Support\Models\Company;
use Nuwave\Lighthouse\Tests\DataLoader\Support\CompanyType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\TaskType;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Tests\DataLoader\Support\CompanyDataFetcher;
use Nuwave\Lighthouse\Tests\DataLoader\Support\UserDataFetcher;
use Nuwave\Lighthouse\Tests\DataLoader\Support\TaskDataFetcher;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Prophecy\Argument;

class DataFetcherTest extends DBTestCase
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
            'company_id' => $this->company->id,
        ]);

        $this->users->each(function ($user) {
            factory(Task::class, 5)->create([
                'user_id' => $user->id,
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
            $graphql->schema()->type('company', CompanyType::class);
            $graphql->schema()->type('user', UserFetcherType::class);
            $graphql->schema()->type('task', TaskType::class);
            $graphql->schema()->query('companyQuery', Support\CompanyQuery::class);
            $graphql->schema()->dataFetcher('company', CompanyDataFetcher::class);
            $graphql->schema()->dataFetcher('user', UserDataFetcher::class);
            $graphql->schema()->dataFetcher('task', TaskDataFetcher::class);
        });
    }

    /**
     * @test
     */
    public function itCanResolveInstanceOfDataFetcher()
    {
        $graphql = app('graphql');
        $this->assertInstanceOf(CompanyDataFetcher::class, $graphql->dataFetcher('company'));
        $this->assertInstanceOf(UserDataFetcher::class, $graphql->dataFetcher('user'));
        $this->assertInstanceOf(TaskDataFetcher::class, $graphql->dataFetcher('task'));

        $this->assertInstanceOf(CompanyDataFetcher::class, dataFetcher('company'));
        $this->assertInstanceOf(UserDataFetcher::class, dataFetcher('user'));
        $this->assertInstanceOf(TaskDataFetcher::class, dataFetcher('task'));
    }

    /**
     * @test
     */
    public function itCanExtractAllFieldsFromQuery()
    {
        $query = $this->getQuery();
        $dataFetcher = $this->prophesize(CompanyDataFetcher::class);
        $this->app->instance(CompanyDataFetcher::class, $dataFetcher->reveal());

        $dataFetcher->resolve(
            Argument::type(Company::class),
            $this->getAllFields()
        )
        ->shouldBeCalled()
        ->willReturn(null);

        $this->executeQuery($query);
    }

    /**
     * @test
     */
    public function itResolveChildDataFetcher()
    {
        $query = $this->getQuery();
        $fields = $this->getAllFields();
        $dataFetcher = $this->prophesize(UserDataFetcher::class);
        $this->app->instance(UserDataFetcher::class, $dataFetcher->reveal());

        $dataFetcher->companyUsers(
            Argument::type(Company::class),
            array_get($fields, 'users.args'),
            array_get($fields, 'users')
        )
        ->shouldBeCalled()
        ->willReturn(null);

        $this->executeQuery($query);
    }

    /**
     * @test
     */
    public function itCanUseDataFetchersToResolveTypes()
    {
        $userDataFetcher = $this->prophesize(UserDataFetcher::class);
        app()->instance(UserDataFetcher::class, $userDataFetcher->reveal());

        $this->executeQuery($this->getQuery());

        $userDataFetcher->loadDataByKey('company', $this->company->id)->shouldHaveBeenCalled();
    }

    /**
     * @test
     * @group failing
     */
    public function itCanResolveNestedData()
    {
        $queries = 0;
        \DB::listen(function ($query) use (&$queries) {
            $queries++;
        });

        $query = $this->executeQuery($this->getQuery());
        $data = array_get($query, 'data.companyQuery');

        $this->assertEquals(3, $queries);
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

    /**
     * Get expected fields from query.
     *
     * @return array
     */
    protected function getAllFields()
    {
        return [
            'name' => [
                'parent' => false,
                'args' => [],
            ],
            'users' => [
                'parent' => true,
                'args' => [
                    'first' => '5',
                ],
                'children' => [
                    'edges' => [
                        'parent' => true,
                        'args' => [],
                        'children' => [
                            'node' => [
                                'parent' => true,
                                'args' => [],
                                'children' => [
                                    'name' => [
                                        'parent' => false,
                                        'args' => [],
                                    ],
                                    'email' => [
                                        'parent' => false,
                                        'args' => [],
                                    ],
                                    'tasks' => [
                                        'parent' => true,
                                        'args' => [
                                            'first' => '2',
                                        ],
                                        'children' => [
                                            'edges' => [
                                                'parent' => true,
                                                'args' => [],
                                                'children' => [
                                                    'node' => [
                                                        'parent' => true,
                                                        'args' => [],
                                                        'children' => [
                                                            'title' => [
                                                                'parent' => false,
                                                                'args' => [],
                                                            ],
                                                            'description' => [
                                                                'parent' => false,
                                                                'args' => [],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

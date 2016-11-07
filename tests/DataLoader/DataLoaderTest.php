<?php

namespace Nuwave\Lighthouse\Tests\DataLoader;

use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Tests\Support\Models\Task;
use Nuwave\Lighthouse\Tests\Support\Models\Company;
use Nuwave\Lighthouse\Tests\DataLoader\Support\CompanyType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\TaskType;
use Nuwave\Lighthouse\Tests\DataLoader\Support\UserType;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Tests\DataLoader\Support\CompanyDataLoader;
use Nuwave\Lighthouse\Tests\DataLoader\Support\UserDataLoader;
use Nuwave\Lighthouse\Tests\DataLoader\Support\TaskDataLoader;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Prophecy\Argument;

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
            $graphql->schema()->type('company', CompanyType::class);
            $graphql->schema()->type('user', UserType::class);
            $graphql->schema()->type('task', TaskType::class);
            $graphql->schema()->query('companyQuery', Support\CompanyQuery::class);
            $graphql->schema()->dataLoader('company', CompanyDataLoader::class);
            $graphql->schema()->dataLoader('user', UserDataLoader::class);
            $graphql->schema()->dataLoader('task', TaskDataLoader::class);
        });
    }

    /**
     * @test
     */
    public function itCanResolveInstanceOfDataLoader()
    {
        $graphql = app('graphql');
        $this->assertInstanceOf(CompanyDataLoader::class, $graphql->dataLoader('company'));
        $this->assertInstanceOf(UserDataLoader::class, $graphql->dataLoader('user'));
        $this->assertInstanceOf(TaskDataLoader::class, $graphql->dataLoader('task'));
    }

    /**
     * @test
     */
    public function itCanExtractAllFieldsFromQuery()
    {
        $query = $this->getQuery();
        $dataLoader = $this->prophesize(CompanyDataLoader::class);
        $this->app->instance(CompanyDataLoader::class, $dataLoader->reveal());

        $dataLoader->resolve(
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
    public function itResolveChildDataLoader()
    {
        $query = $this->getQuery();
        $fields = $this->getAllFields();
        $dataLoader = $this->prophesize(UserDataLoader::class);
        $this->app->instance(UserDataLoader::class, $dataLoader->reveal());

        $dataLoader->companyUsers(
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
    public function itCanUseDataLoadersToResolveTypes()
    {
        $userDataLoader = $this->prophesize(UserDataLoader::class);
        app()->instance(UserDataLoader::class, $userDataLoader->reveal());

        $this->executeQuery($this->getQuery());

        $userDataLoader->loadDataByKey('company', $this->company->id)->shouldHaveBeenCalled();
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
                'args' => []
            ],
            'users' => [
                'parent' => true,
                'args' => [
                    'first' => '5'
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
                                        'args' => []
                                    ],
                                    'email' => [
                                        'parent' => false,
                                        'args' => []
                                    ],
                                    'tasks' => [
                                        'parent' => true,
                                        'args' => [
                                            'first' => '2'
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
                                                                'args' => []
                                                            ],
                                                            'description' => [
                                                                'parent' => false,
                                                                'args' => []
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}

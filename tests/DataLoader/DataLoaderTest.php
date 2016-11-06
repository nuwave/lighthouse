<?php

namespace Nuwave\Lighthouse\Tests\DataLoader;

use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Tests\Support\Models\Task;
use Nuwave\Lighthouse\Tests\Support\Models\Company;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\CompanyType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\TaskType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Tests\DataLoader\Support\CompanyDataLoader;
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
        $app['config']->set('lighthouse.schema.register', function () {
            $graphql = app('graphql');
            $graphql->schema()->type('company', CompanyType::class);
            $graphql->schema()->type('user', UserType::class);
            $graphql->schema()->type('task', TaskType::class);
            $graphql->schema()->query('companyQuery', Support\CompanyQuery::class);
        });
    }

    /**
     * @test
     */
    public function itCanExtractAllFieldsFromQuery()
    {
        $id = $this->encodeGlobalId(CompanyType::class, $this->company->id);
        $query = '{
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
     * Get expected fields from query.
     *
     * @return array
     */
    public function getAllFields()
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

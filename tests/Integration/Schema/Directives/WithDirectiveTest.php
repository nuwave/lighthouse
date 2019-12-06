<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Brand;
use Tests\Utils\Models\Product;
use Tests\Utils\Models\Supplier;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class WithDirectiveTest extends DBTestCase
{
    /**
     * The currently authenticated user.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * The user's tasks.
     *
     * @var \Illuminate\Support\Collection<\Tests\Utils\Models\Task>
     */
    protected $tasks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->tasks = factory(Task::class, 3)->create([
            'user_id' => $this->user->getKey(),
        ]);

        $this->be($this->user);
    }

    public function testCanQueryARelationship(): void
    {
        $this->schema = '
        type User {
            task_count_string: String!
                @with(relation: "tasks")
                @method(name: "getTaskCountAsString")
        }

        type Query {
            user: User @auth
        }
        ';

        /** @var \Tests\Utils\Models\User $user */
        $user = auth()->user();

        $this->assertFalse(
            $user->relationLoaded('tasks')
        );

        $this->graphQL('
        {
            user {
                task_count_string
            }
        }
        ')->assertJsonFragment([
            'task_count_string' => 'User has 3 tasks.',
        ]);

        $this->assertCount(
            3,
            $user->tasks
        );
    }

    public function testCanQueryANestedRelationship()
    {
        $this->schema = '
        type Brand {
            id: Int!
            name: String
            suppliers: [Supplier]
        }

        type Supplier {
            id: Int!
            name: String
            Brand: Brand!
        }

        type Product {
            id: Int!
            preferredSupplier: Supplier! @with(relation: "brand.suppliers")
        }

        type Query {
            products: [Product] @paginate
        }
        ';
        /** @var Brand $brand */
        $brand = factory(Brand::class)->create();
        $supplier1 = factory(Supplier::class)->create();
        $supplier2 = factory(Supplier::class)->create();

        $brand->suppliers()->sync([
            $supplier1->id => ['is_preferred_supplier' => false],
            $supplier2->id => ['is_preferred_supplier' => true],
        ]);

        $product = factory(Product::class)->create(['brand_id' => $brand->id]);

        $this->assertFalse(
            $product->relationLoaded('brand.suppliers')
        );

        $response = $this->graphQL('{
            products(first: 1) {
                data {
                    preferredSupplier {
                        id
                    }
                }
            }
        }
        ');

        $response->assertJson([
            'data' => [
                'products' => [
                    'data' => [
                        'preferredSupplier' => [
                            'id' => $supplier2->id,
                        ],
                    ],
                ],
            ],
      ]);
    }
}

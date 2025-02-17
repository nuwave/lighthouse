<?php declare(strict_types=1);

namespace Tests\Integration\Defer;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Defer\DeferServiceProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;

final class DeferDBTest extends DBTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [DeferServiceProvider::class],
        );
    }

    public function testDeferBelongsToFields(): void
    {
        $company = factory(Company::class)->create();
        assert($company instanceof Company);

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $user->setRelations([]);

        $this->mockResolver($user);

        $this->schema = /** @lang GraphQL */ '
        type Company {
            name: String!
        }

        type User {
            email: String!
            company: Company
        }

        type Query {
            user: User @mock
        }
        ';

        $this->countQueries($queryCount);

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
        {
            user {
                email
                company @defer {
                    name
                }
            }
        }
        ');

        $this->assertSame(1, $queryCount);
        $this->assertCount(2, $chunks);

        $deferredUser = $chunks[0];
        $this->assertSame($user->email, Arr::get($deferredUser, 'data.user.email'));
        $this->assertNull(Arr::get($deferredUser, 'data.user.company'));

        $deferredCompany = $chunks[1];
        $this->assertArrayHasKey('user.company', $deferredCompany);
        $this->assertSame($company->name, Arr::get($deferredCompany['user.company']['data'], 'name'));
    }

    public function testDeferNestedRelationshipFields(): void
    {
        $company = factory(Company::class)->create();
        assert($company instanceof Company);

        $users = factory(User::class, 5)->make();
        foreach ($users as $user) {
            assert($user instanceof User);
            $user->company()->associate($company);
            $user->save();
        }

        $user = $users[0];
        assert($user instanceof User);
        $user->setRelations([]);

        $this->mockResolver($user);

        $this->schema = /** @lang GraphQL */ '
        type Company {
            name: String!
            users: [User] @hasMany
        }

        type User {
            email: String!
            company: Company
        }

        type Query {
            user: User @mock
        }
        ';

        $this->countQueries($queryCount);

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
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
        }
        ');

        $this->assertSame(2, $queryCount);
        $this->assertCount(3, $chunks);

        $deferredUser = $chunks[0];
        $this->assertSame($user->email, Arr::get($deferredUser, 'data.user.email'));
        $this->assertNull(Arr::get($deferredUser, 'data.user.company'));

        $deferredCompany = $chunks[1];
        $this->assertArrayHasKey('user.company', $deferredCompany);
        $this->assertSame($company->name, Arr::get($deferredCompany['user.company']['data'], 'name'));
        $this->assertNull(Arr::get($deferredCompany['user.company'], 'users'));

        $deferredUsers = $chunks[2];
        $this->assertArrayHasKey('user.company.users', $deferredUsers);
        $this->assertCount(5, $deferredUsers['user.company.users']['data']);
        $this->assertSame(
            $users
                ->map(static fn (User $user): array => ['email' => $user->email])
                ->values()
                ->all(),
            $deferredUsers['user.company.users']['data'],
        );
    }

    public function testDeferNestedListFields(): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<array-key, \Tests\Utils\Models\Company> $companies */
        $companies = factory(Company::class, 2)
            ->create()
            ->each(static function (Company $company): void {
                factory(User::class, 3)->create([
                    'company_id' => $company->getKey(),
                ]);
            });

        $this->mockResolver($companies);

        $this->schema = /** @lang GraphQL */ '
        type Company {
            name: String!
            users: [User] @hasMany
        }

        type User {
            email: String!
            company: Company @belongsTo
        }

        type Query {
            companies: [Company] @mock
        }
        ';

        $this->countQueries($queryCount);

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
        {
            companies {
                name
                users @defer {
                    email
                    company @defer {
                        name
                    }
                }
            }
        }
        ');

        $this->assertSame(2, $queryCount);
        $this->assertCount(3, $chunks);

        $deferredCompanies = $chunks[0];

        $company0 = $companies[0];
        $this->assertInstanceOf(Company::class, $company0);
        $this->assertSame($company0->name, Arr::get($deferredCompanies, 'data.companies.0.name'));

        $company1 = $companies[1];
        $this->assertInstanceOf(Company::class, $company1);
        $this->assertSame($company1->name, Arr::get($deferredCompanies, 'data.companies.1.name'));

        $this->assertNull(Arr::get($deferredCompanies, 'data.companies.0.users'));
        $this->assertNull(Arr::get($deferredCompanies, 'data.companies.1.users'));

        $deferredUsers = $chunks[1];
        $companies->each(function (Company $company, int $i) use ($deferredUsers): void {
            $key = "companies.{$i}.users";
            $this->assertArrayHasKey($key, $deferredUsers);

            $this->assertSame(
                $company->users
                    // @phpstan-ignore-next-line type of model is known
                    ->map(static fn (User $user): array => [
                        'email' => $user->email,
                        'company' => null,
                    ])
                    ->all(),
                $deferredUsers[$key]['data'],
            );
        });

        $deferredCompanies = $chunks[2];

        $this->assertCount(6, $deferredCompanies);

        (new Collection($deferredCompanies))->each(function (array $item) use ($companies): void {
            $item = $item['data'];
            $this->assertArrayHasKey('name', $item);

            $this->assertContains(
                $item['name'],
                $companies->pluck('name')->all(),
            );
        });
    }
}

<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Support\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Support\Exceptions\AuthenticationException;

class CanDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itThrowsWhenNotAuthenticated()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        type Query {
            user: User! @can(if: "adminOnly")
        }
        
        type User {
            name: String
        }
        ');
        $type = $schema->getQueryType();
        $fields = $type->config['fields']();
        $resolver = array_get($fields, 'user.resolve');

        $this->expectException(AuthenticationException::class);
        $resolver();
    }

    /**
     * @test
     */
    public function itThrowsIfNotAuthorized()
    {
        $this->be(new User);

        $schema = $this->buildSchemaWithDefaultQuery('
        type Query {
            user: User! @can(if: "adminOnly")
        }
        
        type User {
            name: String
        }
        ');
        $type = $schema->getQueryType();
        $fields = $type->config['fields']();
        $resolver = array_get($fields, 'user.resolve');

        $this->expectException(AuthorizationException::class);
        $resolver();
    }

    /**
     * @test
     */
    public function itPassesAuthIfAuthorized()
    {
        $user = new User;
        $user->name = 'admin';
        $this->be($user);

        $schema = $this->buildSchemaWithDefaultQuery('
        type Query {
            user: User! @can(if: "adminOnly") @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ');
        $type = $schema->getQueryType();
        $fields = $type->config['fields']();
        $resolver = array_get($fields, 'user.resolve');
        $result = $resolver(null, []);

        $this->assertSame('foo', $result->name);
    }

    /**
     * @test
     */
    public function itPassesMultiplePolicies()
    {
        $user = new User;
        $user->name = 'admin';
        $this->be($user);

        $schema = $this->buildSchemaWithDefaultQuery('
        type Query {
            user: User! @can(if: ["adminOnly", "alwaysTrue"]) @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            name: String
        }
        ');
        $type = $schema->getQueryType();
        $fields = $type->config['fields']();
        $resolver = array_get($fields, 'user.resolve');
        $result = $resolver(null, []);

        $this->assertSame('foo', $result->name);
    }

    public function resolveUser()
    {
        $user = new User;
        $user->name = 'foo';
        return $user;
    }
}

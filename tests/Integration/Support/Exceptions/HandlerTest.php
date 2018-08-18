<?php


namespace Tests\Integration\Support\Exceptions;


use Exception;
use Nuwave\Lighthouse\Support\Contracts\Errorable;
use Nuwave\Lighthouse\Support\Exceptions\Error;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class HandlerTest extends DBTestCase
{
    /** @test */
    public function canImplementCustomException()
    {
        $schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        type Query {
            users: [User!]! @field(resolver: "Tests\\\Integration\\\Support\\\Exceptions\\\Resolver@resolverWithErrorable")
        }';

        $query = '{
            users {
                id
            }
        }';

        $result = $this->execute($schema, $query);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals("Resolver failed...", $result['errors'][0]['message']);
    }

    /** @test */
    public function canConvertExceptionToDefaultError()
    {
        $this->app['config']->set('app.debug', false);

        $schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        type Query {
            users: [User!]! @field(resolver: "Tests\\\Integration\\\Support\\\Exceptions\\\Resolver@randomException")
        }';

        $query = '{
            users {
                id
            }
        }';

        $result = $this->execute($schema, $query);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals("Random error encountered.", $result['errors'][0]['message']);
    }

    /** @test */
    public function canConvertExceptionToErrorInDebugging()
    {
        $this->app['config']->set('app.debug', true);


        $schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        type Query {
            users: [User!]! @field(resolver: "Tests\\\Integration\\\Support\\\Exceptions\\\Resolver@customException")
        }';

        $query = '{
            users {
                id
            }
        }';

        $result = $this->execute($schema, $query);
        $this->assertCount(1, $result['errors']);
        $error = $result['errors'][0];

        $this->assertEquals("Our Custom Exception", $error['message']);
        $this->assertEquals(CustomException::class, $error['exception']);
        $this->assertInternalType('int', $error['line']);
        $this->assertEquals(__FILE__, $error['file']);
        $this->assertInternalType('array', $error['trace']);
    }
}

class Resolver
{
    public function resolverWithErrorable()
    {
        throw new ResolverException();
    }

    public function randomException()
    {
        throw new Exception("random message");
    }

    public function customException()
    {
        throw new CustomException("Our Custom Exception");
    }

}

class ResolverException extends Exception implements Errorable {

    public function toError(): Error
    {
        return Error::fromArray([
            'message' => "Resolver failed..."
        ]);
    }
}

class CustomException extends Exception {

}

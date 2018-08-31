<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User;
use Nuwave\Lighthouse\Schema\Context;
use GraphQL\Type\Definition\ResolveInfo;

class InjectDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanInjectDataFromContextIntoArgs()
    {
        $user = new class() extends User {
            public $foo = 'bar';
        };

        $schema = $this->buildSchemaFromString('
        type User {
            foo: String!
        }
        
        type Query {
            user: User! @inject(context: "user.foo", name: "foo") @field(resolver: "' . addslashes(self::class) . '@resolveUser")
        }
        ');

        $query = $schema->getType('Query');
        $resolver = array_get($query->config['fields'](), 'user.resolve');
        $resolver(null, [], new Context(new Request([]), $user), new ResolveInfo([]));
    }

    public function resolveUser($root, $args)
    {
        $this->assertSame('bar', $args['foo']);
    }
}

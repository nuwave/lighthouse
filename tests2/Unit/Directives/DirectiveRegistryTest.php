<?php


namespace Tests\Unit\Directives;


use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\Directives\ArgumentDirective;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective;
use Nuwave\Lighthouse\Support\Contracts\Directives\NodeDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\Scalar\StringType;
use Tests\TestCase;

class DirectiveRegistryTest extends TestCase
{
    /** @test */
    public function can_register_a_directive()
    {
        $directiveRegistry = new DirectiveRegistry();
        $directiveRegistry->add(ExampleDirective::class);

        $this->assertTrue($directiveRegistry->has("Example"));
    }

    /** @test */
    public function can_get_a_directive_from_name()
    {
        $directiveRegistry = new DirectiveRegistry();
        $directiveRegistry->add(ExampleDirective::class);

        $directives = $directiveRegistry->get("Example");

        $this->assertCount(1, $directives);
        $this->assertInstanceOf(ExampleDirective::class, $directives->first());
        $this->assertEquals("Example", $directives->first()->name());
    }

    /** @test */
    public function can_resolve_a_field_directive()
    {
        $schema = '
            type Query {
                example: String @Example
            }
        ';

        $query = '
            query {
                example
            }
        ';

        graphql()->directives()->add(ExampleDirective::class);
        graphql()->build($schema);

        $result = graphql()->execute($query)['data'];
        $this->assertCount(1, $result);
        $this->assertEquals("Handler ran", $result['example']);
    }

    /** @test */
    public function can_resolve_a_field_directive_with_multiple_resolvers()
    {
        $schema = '
            type Query {
                example: String @Example
            }
        ';

        $query = '
            query {
                example
            }
        ';

        graphql()->directives()->add(AfterExampleDirective::class);
        graphql()->directives()->add(ExampleDirective::class);
        graphql()->build($schema);

        $result = graphql()->execute($query)['data'];
        $this->assertCount(1, $result);
        $this->assertEquals("Handler ran and append this.", $result['example']);
    }

    /** @test */
    public function can_resolve_multiple_field_directives()
    {
        $schema = '
            type Query {
                example: String @Example @Other
            }
        ';

        $query = '
            query {
                example
            }
        ';

        graphql()->directives()->add(OtherDirective::class);
        graphql()->directives()->add(ExampleDirective::class);
        graphql()->build($schema);

        $result = graphql()->execute($query)['data'];
        $this->assertCount(1, $result);
        $this->assertEquals("Handler ran and other appending this.", $result['example']);
    }

    /** @test */
    public function can_resolve_multiple_field_directives_reverse_order()
    {
        $schema = '
            type Query {
                example: String @Other @Example
            }
        ';

        $query = '
            query {
                example
            }
        ';

        graphql()->directives()->add(OtherDirective::class);
        graphql()->directives()->add(ExampleDirective::class);
        graphql()->build($schema);

        $result = graphql()->execute($query)['data'];
        $this->assertCount(1, $result);
        $this->assertEquals(" and other appending this.Handler ran", $result['example']);
    }

    /** @test */
    public function can_resolve_a_node_directive()
    {
        $schema = '
            type User @Example {
                name: String!
            }
            
            type Query {
                me: User
            }
        ';

        $query = '
            { __type(name:"User") {
                fields {
                  name
                  description
                  }  
                }
            }
        ';

        graphql()->directives()->add(ExampleDirective::class);
        graphql()->build($schema);

        $result = graphql()->execute($query)['data'];
        $fields = data_get($result, '__type.fields');
        $this->assertCount(2, $fields);

        // Check if the already defined field exist.
        $this->assertEquals([
            'name' => 'name',
            'description' => null
        ], $fields[0]);

        // Check if the field added by node directive exist.
        $this->assertEquals([
            'name' => 'example',
            'description' => "example auto generated field"
        ], $fields[1]);
    }

    /** @test */
    public function can_resolve_multiple_node_directives()
    {
        $schema = '
            type User @Example @Other {
                name: String!
            }
            
            type Query {
                me: User
            }
        ';

        $query = '
            { __type(name:"User") {
                fields {
                  name
                  description
                  }  
                }
            }
        ';

        graphql()->directives()->add(ExampleDirective::class);
        graphql()->directives()->add(OtherDirective::class);
        graphql()->build($schema);

        $result = graphql()->execute($query)['data'];
        $fields = data_get($result, '__type.fields');
        $this->assertCount(3, $fields);

        // Check if the already defined field exist.
        $this->assertEquals([
            'name' => 'name',
            'description' => null
        ], $fields[0]);

        // Check if the field added by example node directive exist.
        $this->assertEquals([
            'name' => 'example',
            'description' => "example auto generated field"
        ], $fields[1]);

        // Check if the field added by other node directive exist.
        $this->assertEquals([
            'name' => 'other',
            'description' => null
        ], $fields[2]);
    }

    /** @test */
    public function can_resolve_arg_directive()
    {
        $schema = '
            type User {
                name: String!
            }
            
            type Query {
                users(name: String @Example): [User]
            }
        ';

        $query = '
            {
                users(name: "Oliver") {
                    name
                }
            }
        ';

        graphql()->directives()->add(ExampleDirective::class);
        graphql()->build($schema);

        $result = graphql()->execute($query)['data'];
        dd("the response was", $result);
        $users = $result['users'];

        $this->assertCount(1, $users);
        $this->assertEquals(['name' => 'new user'], $users[0]);
    }

    /** @test */
    public function can_resolve_multiple_arg_directives()
    {

    }


}

class ExampleDirective implements NodeDirective, FieldDirective, ArgumentDirective
{
    public function name(): string
    {
        return "Example";
    }

    public function handleNode(Collection $fields, Closure $next)
    {
        $fields->put("example", new Field(
           "example",
           "example auto generated field",
           graphql()->schema()->type("String"),
           null,
           null,
           function ($data) {
               dd("example resolver");
           }
        ));
        return $next($fields);
    }

    public function handleField($value, Closure $next)
    {
        return $next($value."Handler ran");
    }

    public function handleArgument($value, Closure $next)
    {
        return $next([
            ['name' => 'new user']
        ]);
    }
}

class AfterExampleDirective implements FieldDirective
{
    public function name(): string
    {
        return "Example";
    }

    public function handleField($value, Closure $next)
    {
        $response = $next($value);
        $response.=" and append this.";

        return $response;
    }
}

class OtherDirective implements FieldDirective, NodeDirective
{
    public function name(): string
    {
        return "Other";
    }

    public function handleField($value, Closure $next)
    {
        $value .= " and other appending this.";

        return $next($value);
    }

    public function handleNode(Collection $fields, Closure $next)
    {
        $fields->put("other", new Field(
            'other',
            null,
            graphql()->schema()->type("String"),
            null,
            null,
            function ($data) {
                dd("other resolver");
            }
        ));

        return $next($fields);
    }
}
<?php


namespace Tests\Unit\Directives\Nodes;


use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\Directives\NodeDirective;
use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\Type;
use Tests\TestCase;

class NodeDirectiveTest extends TestCase
{
    /** @test */
    public function node_directive_can_add_field()
    {
        $schema = '
            type User @AddField {
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
                  type {
                    name
                  }
                }  
            }
        }
        ';

        graphql()->directives()->add(AddFieldDirective::class);
        graphql()->build($schema);

        $result = graphql()->execute($query)['data'];
        $fields = data_get($result, '__type.fields');
        $this->assertCount(2, $fields);

        // Check if the field added by node directive exist.
        $this->assertEquals([
            'name' => 'example',
            'description' => "example auto generated field",
            'type' => [
                'name' => 'User'
            ]
        ], $fields[1]);
    }

    /** @test */
    public function node_directive_can_edit_field()
    {
        $schema = '
            type User @EditField {
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
                  type {
                    name
                  }
                }  
            }
        }
        ';

        graphql()->directives()->add(EditFieldDirective::class);
        graphql()->build($schema);

        $result = graphql()->execute($query)['data'];
        $fields = data_get($result, '__type.fields');
        $this->assertCount(1, $fields);

        // Check if the field added by node directive exist.
        $this->assertEquals([
            'name' => 'name',
            'description' => null,
            'type' => [
                'name' => 'User'
            ]
        ], $fields[0]);
    }
}

class AddFieldDirective implements NodeDirective
{

    public function name()
    {
        return "AddField";
    }

    public function handleNode(Type $type, Closure $next)
    {
        $type->resolvedFields()->put('example', new Field(
            "example",
            "example auto generated field",
            graphql()->schema()->type("User"),
            null,
            null,
            function ($data) {}
        ));
        return $next($type);
    }
}

class EditFieldDirective implements NodeDirective
{

    public function name()
    {
        return "EditField";
    }

    public function handleNode(Type $type, Closure $next)
    {
        $fieldName = $type->resolvedField('name');
        $fieldName->setName('newName');
        $fieldName->setType(graphql()->schema()->type('User'));

        return $next($type);
    }
}
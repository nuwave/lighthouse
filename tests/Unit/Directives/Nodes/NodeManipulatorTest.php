<?php


namespace Tests\Unit\Directives\Nodes;


use Closure;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\ManipulatorInfo;
use Nuwave\Lighthouse\Support\Contracts\Directives\NodeManipulator;
use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\ObjectType;
use Nuwave\Lighthouse\Types\Scalar\StringType;
use Nuwave\Lighthouse\Types\Type;
use Tests\TestCase;

class NodeManipulatorTest extends TestCase
{
    /** @test */
    public function can_add_new_type()
    {
        $schema = '
            type User @addNewType {
                name: String!
            }
        ';

        $this->graphql->directives()->add(AddNewType::class);
        $this->graphql->build($schema);

        $exampleType = $this->graphql->schema()->type('Example');

        $this->assertEquals('Example', $exampleType->name());
        $this->assertNull($exampleType->description());

        $fields = $exampleType->fields();
        $this->assertCount(1, $fields);
        $otherField = $exampleType->field('other');
        $this->assertEquals('other', $otherField->name());
        $this->assertNull($otherField->description());
        $this->assertInstanceOf(StringType::class, $otherField->type());
    }

    /** @test */
    public function can_apply_to_extension_type_only()
    {
        $schema = '
            "A user type"
            type User {
                name: String!
                "Email of the user"
                email: String!
            }
            
            extend type User @changeDescription {
                "Address of the user"
                address: String!
                age: Int!
            }
        ';

        $this->graphql->directives()->add(ChangeDescription::class);
        $this->graphql->build($schema);

        $user = $this->graphql->schema()->type('User');
        $this->assertCount(4, $user->fields());
        $this->assertEquals("A user type", $user->description());

        // Check if the directive hasn't changed the description of our non extend type fields.
        $nameField = $user->field('name');
        $this->assertEquals(null, $nameField->description());

        $emailField = $user->field('email');
        $this->assertEquals("Email of the user", $emailField->description());

        // Check if the directive changed the description of our extended type fields.
        $addressField = $user->field('address');
        $this->assertEquals("new description", $addressField->description());

        $ageField = $user->field('age');
        $this->assertEquals("new description", $ageField->description());
    }
}

class AddNewType implements NodeManipulator
{
    protected $directiveRegistry;

    public function __construct(DirectiveRegistry $directiveRegistry)
    {
        $this->directiveRegistry = $directiveRegistry;
    }

    public function name()
    {
        return "addNewType";
    }

    public function manipulateNode(ManipulatorInfo $info, Closure $next)
    {
        $info->addType(new ObjectType(
            "Example",
            null,
            function () {
                return collect([
                    new Field(
                        $this->directiveRegistry,
                        "other",
                        null,
                        Type::string()
                    )
                ]);
            }
        ));
        return $next($info);
    }
}

class ChangeDescription implements NodeManipulator
{

    public function name()
    {
        return "changeDescription";
    }

    public function manipulateNode(ManipulatorInfo $info, Closure $next)
    {
        $info->type()->fields()->each(function (Field $field) {
            $field->setDescription("new description");
        });
        return $next($info);
    }
}
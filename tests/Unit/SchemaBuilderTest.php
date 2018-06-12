<?php


namespace Tests\Unit;


use Nuwave\Lighthouse\Types\Scalar\BooleanType;
use Nuwave\Lighthouse\Types\EnumType;
use Nuwave\Lighthouse\Types\EnumValueType;
use Nuwave\Lighthouse\Types\Scalar\FloatType;
use Nuwave\Lighthouse\Types\Scalar\IDType;
use Nuwave\Lighthouse\Types\InterfaceType;
use Nuwave\Lighthouse\Types\Scalar\IntType;
use Nuwave\Lighthouse\Types\ListType;
use Nuwave\Lighthouse\Types\NonNullType;
use Nuwave\Lighthouse\Types\ObjectType;
use Nuwave\Lighthouse\Types\Scalar\StringType;
use Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    public function testCanResolveStringType()
    {
        $schema = '   
        type Query {
            "Name of the app."
            appName: String
        }
        ';

        $schema = $this->graphql->build($schema);

        $query = $schema->type('Query');

        $this->assertCount(1, $query->fields());

        $appName = $query->field('appName');
        $this->assertEquals("appName", $appName->name());
        $this->assertEquals("Name of the app.", $appName->description());
        $this->assertInstanceOf(StringType::class, $appName->type());
    }

    public function testCanResolveNonNullStringType()
    {
        $schema = '   
        type Query {
            "Name of the app."
            appName: String!
        }
        ';

        $schema = $this->graphql->build($schema);

        $query = $schema->type('Query');

        $this->assertCount(1, $query->fields());

        $appNameField = $query->field('appName');
        $this->assertEquals("appName", $appNameField->name());
        $this->assertEquals("Name of the app.", $appNameField->description());
        $this->assertInstanceOf(NonNullType::class, $appNameField->type());
        $this->assertInstanceOf(StringType::class, $appNameField->type()->getWrappedType());
    }

    public function testCanResolveIntType()
    {
        $schema = '   
        type Query {
            "Version of the app."
            appVersion: Int
        }
        ';

        $schema = $this->graphql->build($schema);

        $query = $schema->type('Query');

        $this->assertCount(1, $query->fields());

        $appVersionField = $query->field('appVersion');
        $this->assertEquals("appVersion", $appVersionField->name());
        $this->assertEquals("Version of the app.", $appVersionField->description());
        $this->assertInstanceOf(IntType::class, $appVersionField->type());
    }

    public function testCanResolveIdType()
    {
        $schema = '   
        type Query {
            "Version of the app."
            appVersion: ID
        }
        ';

        $schema = $this->graphql->build($schema);

        $query = $schema->type('Query');

        $this->assertCount(1, $query->fields());

        $appVersionField = $query->field('appVersion');
        $this->assertEquals("appVersion", $appVersionField->name());
        $this->assertEquals("Version of the app.", $appVersionField->description());
        $this->assertInstanceOf(IDType::class, $appVersionField->type());
    }

    public function testCanResolveFloatType()
    {
        $schema = '   
        type Query {
            "Version of the app."
            appVersion: Float
        }
        ';

        $schema = $this->graphql->build($schema);

        $query = $schema->type('Query');

        $this->assertCount(1, $query->fields());

        $appVersionField = $query->field('appVersion');
        $this->assertEquals("appVersion", $appVersionField->name());
        $this->assertEquals("Version of the app.", $appVersionField->description());
        $this->assertInstanceOf(FloatType::class, $appVersionField->type());
    }

    public function testCanResolveListOfStringsType()
    {
        $schema = '   
        type Query {
            "List of app domains"
            appDomains: [String]
        }
        ';

        $schema = $this->graphql->build($schema);

        $query = $schema->type('Query');

        $this->assertCount(1, $query->fields());

        $appVersionField = $query->field('appDomains');
        $this->assertEquals("appDomains", $appVersionField->name());
        $this->assertEquals("List of app domains", $appVersionField->description());
        $this->assertInstanceOf(ListType::class, $appVersionField->type());
        $this->assertInstanceOf(StringType::class, $appVersionField->type()->getWrappedType());
    }

    public function testCanResolveBooleanType()
    {
        $schema = '   
        type Query {
            "Is running on staging server"
            staging: Boolean
        }
        ';

        $schema = $this->graphql->build($schema);

        $query = $schema->type('Query');

        $this->assertCount(1, $query->fields());

        $appVersionField = $query->field('staging');
        $this->assertEquals("staging", $appVersionField->name());
        $this->assertEquals("Is running on staging server", $appVersionField->description());
        $this->assertInstanceOf(BooleanType::class, $appVersionField->type());
    }

    public function testCanResolveEnumType()
    {
        $schema = '   
        "A enum of Roles a user can have in the system"
        enum Role {
            "Company administrator."
            admin

            "Company employee."
            employee
        }
        ';

        $schema = $this->graphql->build($schema);

        /** @var EnumType $role */
        $role = $schema->type('Role');


        $this->assertEquals("Role", $role->name());
        $this->assertEquals("A enum of Roles a user can have in the system", $role->description());
        $this->assertCount(2, $role->values());

        $admin = $role->value('admin');
        $this->assertEquals("admin", $admin->name());
        $this->assertEquals("Company administrator.", $admin->description());

        $employee = $role->value('employee');
        $this->assertEquals("employee", $employee->name());
        $this->assertEquals("Company employee.", $employee->description());
    }

    public function testCanResolveObjectType()
    {
        $schema = '
        "Defines the Foo type."
        type Foo {
            "bar attribute of Foo"
            bar: String!
        }
        ';

        $schema = $this->graphql->build($schema);

        $foo = $schema->type('Foo');

        $this->assertInstanceOf(ObjectType::class, $foo);
        $this->assertEquals("Foo", $foo->name());
        $this->assertEquals("Defines the Foo type.", $foo->description());
        $this->assertCount(1, $foo->fields());

        $barField = $foo->field('bar');

        $this->assertEquals("bar", $barField->name());
        $this->assertEquals("bar attribute of Foo", $barField->description());
        $this->assertInstanceOf(NonNullType::class, $barField->type());
        $this->assertInstanceOf(StringType::class, $barField->type()->getWrappedType());
    }

    public function testCanResolveObjectTypeRecursively()
    {
        $schema = '
        "Defines the Foo type."
        type Foo {
            "bar attribute of Foo"
            bar: Foo
        }
        ';

        $schema = $this->graphql->build($schema);

        $foo = $schema->type('Foo');

        $this->assertInstanceOf(ObjectType::class, $foo);
        $this->assertEquals("Foo", $foo->name());
        $this->assertEquals("Defines the Foo type.", $foo->description());
        $this->assertCount(1, $foo->fields());

        $barField = $foo->field('bar');

        $this->assertEquals("bar", $barField->name());
        $this->assertEquals("bar attribute of Foo", $barField->description());
        $this->assertInstanceOf(ObjectType::class, $barField->type());
    }

    public function testCanResolveInterfaceTypes()
    {
        $schema = '
        "Defines the Foo interface."
        interface Foo {
            "bar is baz"
            bar: String
            
            "loops"
            ho: Foo
        }
        ';

        $schema = $this->graphql->build($schema);

        $foo = $schema->type('Foo');

        $this->assertInstanceOf(InterfaceType::class, $foo);
        $this->assertEquals("Foo", $foo->name());
        $this->assertEquals("Defines the Foo interface.", $foo->description());
        $this->assertCount(2, $foo->fields());

        $barField = $foo->field('bar');

        $this->assertEquals("bar", $barField->name());
        $this->assertEquals("bar is baz", $barField->description());
        $this->assertInstanceOf(StringType::class, $barField->type());

        $barField = $foo->field('ho');

        $this->assertEquals("ho", $barField->name());
        $this->assertEquals("loops", $barField->description());
        $this->assertInstanceOf(InterfaceType::class, $barField->type());
    }

    public function testCanResolveScalarType()
    {
        $schema = '
        "Defines our scala DataTime"
        scalar DateTime
        ';

        $schema = $this->graphql->build($schema);

        $dateTime = $schema->type('DateTime');

        $this->assertEquals("DateTime", $dateTime->name());
        $this->assertEquals("Defines our scala DataTime", $dateTime->description());
        $this->assertCount(0, $dateTime->fields());
    }

    public function testCanResolveInputObjectType()
    {
        $schema = '
        "Defines our input for creating a Foo object"
        input CreateFoo {
            "The foo string."
            foo: String!
            bar: Int
        }
        ';

        $schema = $this->graphql->build($schema);

        $createFoo = $schema->type('CreateFoo');

        $this->assertEquals("CreateFoo", $createFoo->name());
        $this->assertEquals("Defines our input for creating a Foo object", $createFoo->description());
        $this->assertCount(2, $createFoo->fields());

        $fooField = $createFoo->field("foo");
        $this->assertEquals("foo", $fooField->name());
        $this->assertEquals("The foo string.", $fooField->description());
        $this->assertInstanceOf(NonNullType::class, $fooField->type());
        $this->assertInstanceOf(StringType::class, $fooField->type()->getWrappedType());

        $fooField = $createFoo->field("bar");
        $this->assertEquals("bar", $fooField->name());
        $this->assertEquals("", $fooField->description());
        $this->assertInstanceOf(IntType::class, $fooField->type());
    }

    public function testCanResolveSimpleMutation()
    {
        $schema = '
        "A comment about our mutation"
        type Mutation {
            "A comment for our mutation endpoint"
            foo(
                "bar description"
                bar: String! 
                baz: String
                ): String
        }
        ';

        $schema = $this->graphql->build($schema);

        $mutation = $schema->type('Mutation');
        $this->assertEquals("Mutation", $mutation->name());
        $this->assertEquals("A comment about our mutation", $mutation->description());
        $this->assertCount(1, $mutation->fields());

        $foo = $mutation->field("foo");
        $this->assertEquals("foo", $foo->name());
        $this->assertEquals("A comment for our mutation endpoint", $foo->description());
        $this->assertInstanceOf(StringType::class, $foo->type());
        $this->assertCount(2, $foo->arguments());

        $barArg = $foo->argument('bar');
        $this->assertEquals("bar", $barArg->name());
        $this->assertEquals("bar description", $barArg->description());
        $this->assertInstanceOf(NonNullType::class, $barArg->type());
        $this->assertInstanceOf(StringType::class, $barArg->type()->getWrappedType());

        $bazArg = $foo->argument("baz");
        $this->assertEquals("baz", $bazArg->name());
        $this->assertEquals("", $bazArg->description());
        $this->assertInstanceOf(StringType::class, $bazArg->type());
    }

    public function testCanResolveQuery()
    {
        $schema = '
        "A comment on query."
        type Query {
            foo(bar: String! baz: String): String
        }
        ';

        $schema = $this->graphql->build($schema);

        $mutation = $schema->type('Query');
        $this->assertEquals("Query", $mutation->name());
        $this->assertEquals("A comment on query.", $mutation->description());
        $this->assertCount(1, $mutation->fields());

        $foo = $mutation->field("foo");
        $this->assertEquals("foo", $foo->name());
        $this->assertEquals("", $foo->description());
        $this->assertInstanceOf(StringType::class, $foo->type());
        $this->assertCount(2, $foo->arguments());

        $barArg = $foo->argument('bar');
        $this->assertEquals("bar", $barArg->name());
        $this->assertEquals("", $barArg->description());
        $this->assertInstanceOf(NonNullType::class, $barArg->type());
        $this->assertInstanceOf(StringType::class, $barArg->type()->getWrappedType());

        $bazArg = $foo->argument("baz");
        $this->assertEquals("baz", $bazArg->name());
        $this->assertEquals("", $bazArg->description());
        $this->assertInstanceOf(StringType::class, $bazArg->type());
    }

    public function testCanResolveExtendObjectType()
    {
        $schema = '
        type Foo {
            bar: String!
        }
        
        extend type Foo {
            baz: String!
        }
        ';

        $schema = $this->graphql->build($schema);

        $foo = $schema->type('Foo');
        $this->assertEquals("Foo", $foo->name());
        $this->assertCount(2, $foo->fields());

        $barField = $foo->field("bar");
        $this->assertEquals("bar", $barField->name());
        $this->assertInstanceOf(NonNullType::class, $barField->type());
        $this->assertInstanceOf(StringType::class, $barField->type()->getWrappedType());

        $bazField = $foo->field("baz");
        $this->assertEquals("baz", $bazField->name());
        $this->assertInstanceOf(NonNullType::class, $bazField->type());
        $this->assertInstanceOf(StringType::class, $bazField->type()->getWrappedType());
    }

    public function testCanResolveExtendQuery()
    {
        $schema = '
        type Query {
            foo: String!
        }
        extend type Query {
            bar: String!
        }
        ';

        $schema = $this->graphql->build($schema);

        $query = $schema->type('Query');
        $this->assertEquals("Query", $query->name());

        $barField = $query->field("foo");
        $this->assertEquals("foo", $barField->name());
        $this->assertInstanceOf(NonNullType::class, $barField->type());
        $this->assertInstanceOf(StringType::class, $barField->type()->getWrappedType());

        $bazField = $query->field("bar");
        $this->assertEquals("bar", $bazField->name());
        $this->assertInstanceOf(NonNullType::class, $bazField->type());
        $this->assertInstanceOf(StringType::class, $bazField->type()->getWrappedType());
    }

    public function testCanResolveExtendMutation()
    {
        $schema = '
        type Mutation {
            foo: String!
        }
        extend type Mutation {
            bar: String!
        }
        ';

        $schema = $this->graphql->build($schema);

        $mutation = $schema->type('Mutation');
        $this->assertEquals("Mutation", $mutation->name());

        $barField = $mutation->field("foo");
        $this->assertEquals("foo", $barField->name());
        $this->assertInstanceOf(NonNullType::class, $barField->type());
        $this->assertInstanceOf(StringType::class, $barField->type()->getWrappedType());

        $bazField = $mutation->field("bar");
        $this->assertEquals("bar", $bazField->name());
        $this->assertInstanceOf(NonNullType::class, $bazField->type());
        $this->assertInstanceOf(StringType::class, $bazField->type()->getWrappedType());
    }

    public function testCanResolveQueryAndMutation()
    {
        $schema = '
        type Query {
            foo: String!
        }
        type Mutation {
            foo: String!
        }
        ';

        $schema = $this->graphql->build($schema);

        $query = $schema->type('Query');
        $this->assertEquals("Query", $query->name());

        $barField = $query->field("foo");
        $this->assertEquals("foo", $barField->name());
        $this->assertInstanceOf(NonNullType::class, $barField->type());
        $this->assertInstanceOf(StringType::class, $barField->type()->getWrappedType());

        $mutation = $schema->type('Mutation');
        $this->assertEquals("Mutation", $mutation->name());


        $bazField = $mutation->field("foo");
        $this->assertEquals("foo", $bazField->name());
        $this->assertInstanceOf(NonNullType::class, $bazField->type());
        $this->assertInstanceOf(StringType::class, $bazField->type()->getWrappedType());
    }

    public function testCanGetNodeDirectiveFromScalarTypeWithStringArg()
    {
        $schema = '
            scalar Email @scalar(class: "Email")
        ';

        $schema = $this->graphql->build($schema);

        $directive = $schema->type('Email')->directive('scalar');
        $this->assertEquals("scalar", $directive->name());
        $this->assertCount(1, $directive->arguments());


        $classArg = $directive->argument("class");
        $this->assertEquals("class", $classArg->name());
        $this->assertInstanceOf(StringType::class, $classArg->type());
        $this->assertEquals("Email", $classArg->defaultValue());
    }
}

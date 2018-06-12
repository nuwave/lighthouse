<?php


namespace Tests\Unit\Directives;


use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\Directives\ArgumentDirective;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective;
use Nuwave\Lighthouse\Support\Contracts\Directives\NodeDirective;
use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\Scalar\StringType;
use Nuwave\Lighthouse\Types\Type;
use Tests\TestCase;

class DirectiveRegistryTest extends TestCase
{
    public function testCanRegisterADirective()
    {
        $directiveRegistry = new DirectiveRegistry();
        $directiveRegistry->add(ExampleDirective::class);

        $this->assertTrue($directiveRegistry->has("Example"));
    }

    public function testCanGetADirectiveFromName()
    {
        $directiveRegistry = new DirectiveRegistry();
        $directiveRegistry->add(ExampleDirective::class);

        $directives = $directiveRegistry->get("Example");

        $this->assertCount(1, $directives);
        $this->assertInstanceOf(ExampleDirective::class, $directives->first());
        $this->assertEquals("Example", $directives->first()->name());
    }

    public function testCanResolveAFieldDirective()
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

        $this->graphql->directives()->add(ExampleDirective::class);
        $this->graphql->build($schema);

        $result = $this->graphql->execute($query)['data'];
        $this->assertCount(1, $result);
        $this->assertEquals("Handler ran", $result['example']);
    }

    public function testCanResolveAFieldDirectiveWithMultipleResolvers()
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

        $this->graphql->directives()->add(AfterExampleDirective::class);
        $this->graphql->directives()->add(ExampleDirective::class);
        $this->graphql->build($schema);

        $result = $this->graphql->execute($query)['data'];
        $this->assertCount(1, $result);
        $this->assertEquals("Handler ran and append this.", $result['example']);
    }

    public function testCanResolveMultipleFieldDirectives()
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

        $this->graphql->directives()->add(OtherDirective::class);
        $this->graphql->directives()->add(ExampleDirective::class);
        $this->graphql->build($schema);

        $result = $this->graphql->execute($query)['data'];
        $this->assertCount(1, $result);
        $this->assertEquals("Handler ran and other appending this.", $result['example']);
    }

    public function testCanResolveMultipleFieldDirectivesReverseOrder()
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

        $this->graphql->directives()->add(OtherDirective::class);
        $this->graphql->directives()->add(ExampleDirective::class);
        $this->graphql->build($schema);

        $result = $this->graphql->execute($query)['data'];
        $this->assertCount(1, $result);
        $this->assertEquals(" and other appending this.Handler ran", $result['example']);
    }

    public function testCanResolveANodeDirective()
    {
    }

    public function testCanResolveMultipleNodeDirectives()
    {

    }

    public function testCanResolveArgDirective()
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

        $this->graphql->directives()->add(ExampleDirective::class);
        $this->graphql->build($schema);

        $result = $this->graphql->execute($query)['data'];
        $users = $result['users'];

        $this->assertCount(1, $users);
        $this->assertEquals(['name' => 'new user'], $users[0]);
    }

    public function testCanResolveMultipleArgDirectives()
    {

    }


}

class ExampleDirective implements FieldDirective, ArgumentDirective
{
    public function name(): string
    {
        return "Example";
    }


    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {
        $resolveInfo->result($resolveInfo->result()."Handler ran");

        return $next($resolveInfo);
    }

    public function handleArgument(ResolveInfo $resolveInfo, Closure $next)
    {
        $resolveInfo->result([
            ['name' => 'new user']
        ]);
        return $next($resolveInfo);
    }
}

class AfterExampleDirective implements FieldDirective
{
    public function name(): string
    {
        return "Example";
    }

    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {
        $response = $next($resolveInfo);
        $resolveInfo->result($resolveInfo->result()." and append this.");

        return $response;
    }
}

class OtherDirective implements FieldDirective
{
    public function name(): string
    {
        return "Other";
    }

    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {
        $resolveInfo->result($resolveInfo->result()." and other appending this.");

        return $next($resolveInfo);
    }
}

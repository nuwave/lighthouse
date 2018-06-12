<?php


namespace Tests\Unit\Directives\Fields;


use Nuwave\Lighthouse\Schema\Directives\Fields\FieldDirective;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Support\Exceptions\InvalidArgsException;
use Tests\TestCase;

class FieldDirectiveTest extends TestCase
{
    public function testCanResolveWithResolverArg()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo@bar")
        }
        ';

        $this->graphql->directives()->add(FieldDirective::class);
        $schema = $this->graphql->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->assertEquals('foo', $resolver()->result()['bar']);
    }

    public function testCanResolveWithClassMethodArgs()
    {
        $schema = '
        type Foo {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "bar")
        }
        ';

        $this->graphql->directives()->add(FieldDirective::class);
        $schema = $this->graphql->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->assertEquals('foo', $resolver()->result()['bar']);
    }

    public function testCanResolveWithArgsArgument()
    {
        $schema = '
        type Foo {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "baz" args:["foo.baz"])
        }
        ';

        $this->graphql->directives()->add(FieldDirective::class);
        $schema = $this->graphql->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        //TODO: make assertions
    }

    public function testCanThrowErrorIfNoClassIsDefined()
    {
        $schema = '
        type Foo {
            bar: String! @field(method: "bar")
        }
        ';

        $this->graphql->directives()->add(FieldDirective::class);
        $schema = $this->graphql->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    public function testCanThrowErrorIfNoMethodIsDefined()
    {
        $schema = '
        type Foo {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo")
        }
        ';

        $this->graphql->directives()->add(FieldDirective::class);
        $schema = $this->graphql->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    public function testCanThrowErrorIfNoClassIsDefinedUsingResolverArg()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"bar")
        }
        ';

        $this->graphql->directives()->add(FieldDirective::class);
        $schema = $this->graphql->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    public function testCanThrowErrorIfNoClassIsDefinedUsingResolverArgWithAtSign()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"@bar")
        }
        ';

        $this->graphql->directives()->add(FieldDirective::class);
        $schema = $this->graphql->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    public function testCanThrowErrorIfNoMethodIsDefinedUsingResolverArg()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo")
        }
        ';

        $this->graphql->directives()->add(FieldDirective::class);
        $schema = $this->graphql->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    public function testCanThrowErrorIfNoMethodIsDefinedUsingResolverArgWithAtSign()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo@")
        }
        ';

        $this->graphql->directives()->add(FieldDirective::class);
        $schema = $this->graphql->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }
}

<?php


namespace Tests\Unit\Directives\Fields;


use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Support\Exceptions\InvalidArgsException;
use Tests\TestCase;

class FieldDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function can_resolve_with_resolver_arg()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo@bar")
        }
        ';

        $schema = graphql()->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->assertEquals('foo', $resolver()->result()['bar']);
    }

    /**
     * @test
     */
    public function can_resolve_with_class_method_args()
    {
        $schema = '
        type Foo {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "bar")
        }
        ';

        $schema = graphql()->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->assertEquals('foo', $resolver()->result()['bar']);
    }

    /**
     * @test
     */
    public function can_resolve_with_args_argument()
    {
        $schema = '
        type Foo {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "baz" args:["foo.baz"])
        }
        ';

        $schema = graphql()->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        //TODO: make assertions
    }

    /** @test */
    public function can_throw_error_if_no_class_is_defined()
    {
        $schema = '
        type Foo {
            bar: String! @field(method: "bar")
        }
        ';

        $schema = graphql()->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    /**
     * @test
     */
    public function can_throw_error_if_no_method_is_defined()
    {
        $schema = '
        type Foo {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo")
        }
        ';

        $schema = graphql()->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    /** @test */
    public function can_throw_error_if_no_class_is_defined_using_resolver_arg()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"bar")
        }
        ';

        $schema = graphql()->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    /** @test */
    public function can_throw_error_if_no_class_is_defined_using_resolver_arg_with_at_sign()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"@bar")
        }
        ';

        $schema = graphql()->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    /**
     * @test
     */
    public function can_throw_error_if_no_method_is_defined_using_resolver_arg()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo")
        }
        ';

        $schema = graphql()->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }

    /**
     * @test
     */
    public function can_throw_error_if_no_method_is_defined_using_resolver_arg_with_at_sign()
    {
        $schema = '
        type Foo {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo@")
        }
        ';

        $schema = graphql()->build($schema);

        $fieldBar = $schema->type('Foo')->field('bar');
        $resolver = $fieldBar->resolver(new ResolveInfo($fieldBar));

        $this->expectException(InvalidArgsException::class);
        $resolver()->result();
    }
}
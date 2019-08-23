<?php

namespace Tests\Unit\Execution\Arguments;

use GraphQL\Type\Definition\ResolveInfo;
use Tests\TestCase;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputObjectField;
use Nuwave\Lighthouse\Execution\Arguments\TypedArgs;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedAfter;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedBefore;

class TypedArgsTest extends TestCase
{
    /**
     * @test
     */
    public function itReturnsDefinition(): void
    {
        $fooDefinition = new FieldArgument([
            'type' => ScalarType::string(),
            'name' => 'foo',
        ]);
        $typedArgs = TypedArgs::fromArgs(
            ['foo' => 'bar'],
            [
                $fooDefinition,
            ]
        );

        $this->assertSame(
            $fooDefinition,
            $typedArgs->definition('foo')
        );

        $this->assertNull($typedArgs->definition('bar'));
    }

    /**
     * @test
     */
    public function itReturnsType(): void
    {
        $fooType = ScalarType::string();
        $typedArgs = TypedArgs::fromArgs(
            ['foo' => 'bar'],
            [
                new FieldArgument([
                    'type' => $fooType,
                    'name' => 'foo',
                ]),
            ]
        );

        $this->assertSame(
            $fooType,
            $typedArgs->type('foo')
        );

        $this->assertNull($typedArgs->type('bar'));
    }

    /**
     * @test
     */
    public function itLazilyConvertsChildrenToTypedArgs()
    {
        $input = new InputObjectType([
            'name' => 'InputType',
            'fields' => [
                [
                    'name' => 'child',
                    'type' => ScalarType::string(),
                ],
            ],
        ]);

        $typedArgs = TypedArgs::fromArgs(
            [
                'input' => [
                    'child' => 'asdf',
                ],
            ],
            [
                new InputObjectField([
                    'name' => 'input',
                    'type' => $input,
                ]),
            ]
        );

        $this->assertCount(1, $typedArgs);

        foreach ($typedArgs as $key => $arg) {
            $this->assertInstanceOf(
                TypedArgs::class,
                $arg
            );
        }

        $this->assertInstanceOf(TypedArgs::class, $typedArgs['input']);
    }

    /**
     * @test
     */
    public function itPartitionsArgs(): void
    {
        $beforeExtension = new ArgumentExtensions();
        $beforeExtension->resolveBefore = new Before();

        $afterInnerType = ScalarType::string();
        $afterType = new InputObjectType([
            'name' => 'AfterInput',
            'fields' => [
                [
                    'name' => 'bar',
                    'type' => $afterInnerType,
                ],
            ],
        ]);
        $afterExtension = new ArgumentExtensions();
        $afterExtension->resolveBefore = new After();
        $typedArgs = TypedArgs::fromArgs(
            [
                'regular' => 'asdf',
                'before' => 'asdf',
                'after' => [
                    'bar' => 'baz',
                ],
            ],
            [
                new FieldArgument([
                    'type' => ScalarType::string(),
                    'name' => 'regular',
                ]),
                new FieldArgument([
                    'type' => ScalarType::string(),
                    'name' => 'before',
                    'lighthouse' => $beforeExtension,
                ]),
                new FieldArgument([
                    'type' => $afterType,
                    'name' => 'after',
                    'lighthouse' => $afterExtension,
                ]),
            ]
        );

        [$before, $regular, $after] = $typedArgs->partitionResolverInputs();

        $this->assertSame(
            ['before' => 'asdf'],
            $before
        );

        $this->assertSame(
            ['regular' => 'asdf'],
            $regular
        );

        /** @var TypedArgs $afterArg */
        $afterArg = $after['after'];
        $this->assertInstanceOf(
            TypedArgs::class,
            $afterArg
        );
        $this->assertSame(
            $afterInnerType,
            $afterArg->type('bar')
        );
        $this->assertSame(
            'baz',
            $afterArg['bar']
        );
    }
}

class Before implements ResolveNestedBefore
{
    public function __invoke($root, $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
    }
}

class After implements ResolveNestedAfter
{
    public function __invoke($root, $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        // TODO: Implement __invoke() method.
    }
}

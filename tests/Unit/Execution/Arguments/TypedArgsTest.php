<?php

namespace Tests\Unit\Execution\Arguments;

use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedAfter;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedBefore;
use Nuwave\Lighthouse\Execution\Arguments\TypedArgs;
use Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\TestCase;

class TypedArgsTest extends TestCase
{
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
                    'name' => 'foo'
                ])
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
                ]
            ]
        ]);

        $typedArgs = TypedArgs::fromArgs(
            [
                'input' => [
                    'child' => 'asdf'
                ]
            ],
            [
                new InputObjectField([
                    'name' => 'input',
                    'type' => $input
                ])
            ]
        );

        $this->assertCount(1, $typedArgs);

        foreach($typedArgs as $key => $arg){
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
        $beforeExtension->resolver = new Before();

        $afterInnerType = ScalarType::string();
        $afterType = new InputObjectType([
            'name' => 'AfterInput',
            'fields' => [
                [
                    'name' => 'bar',
                    'type' => $afterInnerType,
                ]
            ]
        ]);
        $afterExtension = new ArgumentExtensions();
        $afterExtension->resolver = new After();
        $typedArgs = TypedArgs::fromArgs(
            [
                'regular' => 'asdf',
                'before' => 'asdf',
                'after' => [
                    'bar' => 'baz'
                ]
            ],
            [
                new FieldArgument([
                    'type' => ScalarType::string(),
                    'name' => 'regular'
                ]),
                new FieldArgument([
                    'type' => ScalarType::string(),
                    'name' => 'before',
                    'lighthouse' => $beforeExtension
                ]),
                new FieldArgument([
                    'type' => $afterType,
                    'name' => 'after',
                    'lighthouse' => $afterExtension
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

class Before implements ResolveNestedBefore {

    public function resolveBefore($root, $value, GraphQLContext $context)
    {
    }
}

class After implements ResolveNestedAfter {

    public function resolveBefore($root, $value, GraphQLContext $context)
    {
    }
}

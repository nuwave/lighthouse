<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\Factories\RuleFactory;

class RuleFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function itCanGenerateRulesForMutationArguments()
    {
        $documentAST = ASTBuilder::generate('
        type Mutation {
            createUser(email: String @rules(apply: ["required", "email"])): String
        }
        ');

        list($rules, $messages) = RuleFactory::build(
            'createUser',
            'Mutation',
            [],
            $documentAST
        );

        $this->assertSame([
            'email' => ['required', 'email'],
        ], $rules);

        $this->assertSame([], $messages);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForInputArguments()
    {
        $documentAST = ASTBuilder::generate('
        input UserInput {
            email: String @rules(apply: ["required", "email"])
        }
        
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }
        ');

        $variables = [
            'input' => [
                'email' => 'foo',
            ],
        ];

        list($rules, $messages) = RuleFactory::build(
            'createUser',
            'Mutation',
            $variables,
            $documentAST
        );

        $this->assertEquals(
            [
                'input' => ['required'],
                'input.email' => ['required', 'email'],
            ],
            $rules
        );

        $this->assertSame([], $messages);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForNestedInputArguments()
    {
        $documentAST = ASTBuilder::generate('
        input AddressInput {
            street: String @rules(apply: ["required"])
            primary: Boolean @rules(
                apply: ["required"]
                messages: { 
                    required: "foobar" 
                }
            )
        }
        
        input UserInput {
            email: String @rules(apply: ["required", "email"])
            address: AddressInput @rules(apply: ["required"])
        }
        
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }
        ');

        $variables = [
            'input' => [
                'address' => [
                    'street' => 'bar',
                ],
            ],
        ];

        list($rules, $messages) = RuleFactory::build(
            'createUser',
            'Mutation',
            $variables,
            $documentAST
        );

        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
            'input.address' => ['required'],
            'input.address.street' => ['required'],
            'input.address.primary' => ['required'],
        ], $rules);

        $this->assertSame([
            'input.address.primary.required' => 'foobar',
        ], $messages);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForNestedInputArgumentLists()
    {
        $documentAST = ASTBuilder::generate('
        input AddressInput {
            street: String @rules(apply: ["required"])
            primary: Boolean @rules(
                apply: ["required"]
                messages: { 
                    required: "foobar" 
                }
            )
        }
        
        input UserInput {
            email: String @rules(apply: ["required", "email"])
            address: [AddressInput] @rules(apply: ["required"])
        }
        
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }
        ');

        $variables = [
            'input' => [
                'address' => [
                    'street' => 'bar',
                ],
            ],
        ];

        list($rules, $messages) = RuleFactory::build(
            'createUser',
            'Mutation',
            $variables,
            $documentAST
        );

        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
            'input.address' => ['required'],
            'input.address.*.street' => ['required'],
            'input.address.*.primary' => ['required'],
        ], $rules);

        $this->assertSame([
            'input.address.*.primary.required' => 'foobar',
        ], $messages);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForSelfReferencingInputArguments()
    {
        $documentAST = ASTBuilder::generate('
        input Setting {
            option: String @rules(apply: ["required"])
            value: String @rules(
                apply: ["required"]
                messages: {
                    required: "foobar"
                }
            )
            setting: Setting
        }
        
        input UserInput {
            email: String @rules(apply: ["required", "email"])
            settings: [Setting] @rules(apply: ["required"])
        }
        
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }
        ');

        $variables = [
            'input' => [
                'settings' => [
                    [
                        'option' => 'foo',
                        'value' => 'bar',
                        'setting' => [
                            'option' => 'bar',
                            'value' => 'baz',
                        ],
                    ],
                ],
            ],
        ];

        list($rules, $messages) = RuleFactory::build(
            'createUser',
            'Mutation',
            $variables,
            $documentAST
        );

        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
            'input.settings' => ['required'],
            'input.settings.*.option' => ['required'],
            'input.settings.*.value' => ['required'],
            'input.settings.*.setting.option' => ['required'],
            'input.settings.*.setting.value' => ['required'],
        ], $rules);

        $this->assertEquals([
            'input.settings.*.value.required' => 'foobar',
            'input.settings.*.setting.value.required' => 'foobar',
        ], $messages);
    }

    /**
     * @test
     */
    public function itAlwaysGeneratesRequiredRules()
    {
        $documentAST = ASTBuilder::generate('
        type Mutation {
            createFoo(required: String @rules(apply: ["required"])): String
        }
        ');

        list($rules, $messages) = RuleFactory::build(
            'createFoo',
            'Mutation',
            [],
            $documentAST
        );

        $this->assertSame([
            'required' => ['required'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itAlwaysGeneratesRulesForRequiredNestedInputs()
    {
        $documentAST = ASTBuilder::generate('
        input FooInput {
            required: String @rules(apply: ["required"])
        }
        
        type Mutation {
            createFoo(
                requiredSDL: FooInput!
                requiredRules: FooInput @rules(apply: ["required"])
                requiredBoth: FooInput! @rules(apply: ["required"])
            ): String
        }
        ');

        list($rules, $messages) = RuleFactory::build(
            'createFoo',
            'Mutation',
            [],
            $documentAST
        );

        $this->assertEquals([
            'requiredRules' => ['required'],
            'requiredSDL.required' => ['required'],
            'requiredBoth' => ['required'],
            'requiredBoth.required' => ['required'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itGeneratesRequiredNestedRulesWhenParentIsGiven()
    {
        $documentAST = ASTBuilder::generate('
        input FooInput {
            self: FooInput
            required: String @rules(
                apply: ["required"]
                messages: {
                    required: "foobar"
                }
            )
        }
        
        type Mutation {
            createFoo(input: FooInput @rules(apply: ["required"])): String
        }
        ');

        $variables = [
            'input' => [
                'self' => [],
            ],
        ];

        list($rules, $messages) = RuleFactory::build(
            'createFoo',
            'Mutation',
            $variables,
            $documentAST
        );

        $this->assertEquals([
            'input' => ['required'],
            'input.required' => ['required'],
            'input.self.required' => ['required'],
        ], $rules);

        $this->assertEquals([
            'input.required.required' => 'foobar',
            'input.self.required.required' => 'foobar',
        ], $messages);
    }

    /**
     * @test
     */
    public function itHandlesNestedRulesOnQueriesAndMutations()
    {
        $documentAST = ASTBuilder::generate('
        input Foo {
            bar: Int @rules(apply: ["required"])
        }
        
        type Mutation {
            foo(input: Foo): String
        }
        
        type Query {
            foo(input: Foo): String
        }
        ');

        $variables = [
            'input' => [
                'bar' => 42,
            ],
        ];

        $mutationRules = RuleFactory::build(
            'foo',
            'Mutation',
            $variables,
            $documentAST
        );

        $queryRules = RuleFactory::build(
            'foo',
            'Query',
            $variables,
            $documentAST
        );

        $this->assertSame($mutationRules, $queryRules);
    }
}

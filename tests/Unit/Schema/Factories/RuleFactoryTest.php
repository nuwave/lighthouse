<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Factories\RuleFactory;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class RuleFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function itExtractsRulesFromAnInput()
    {
        $inputDefinition = PartialParser::inputValueDefinition('
        foo: String @rules(apply: ["email"], messages: { email: "bar" })
        ');

        $result = RuleFactory::getRulesAndMessages($inputDefinition);

        $this->assertSame(
            [
                [
                    'foo' => [
                        'email',
                    ],
                ],
                [
                    'foo.email' => 'bar',
                ],
            ],
            $result
        );
    }

    /**
     * @test
     */
    public function itUsesRulesOnArrayIfInputIsList()
    {
        $inputDefinition = PartialParser::inputValueDefinition('
        foo: [String] @rules(apply: ["email"], messages: { email: "bar" })
        ');

        $result = RuleFactory::getRulesAndMessages($inputDefinition);

        $this->assertSame(
            [
                [
                    '*.foo' => [
                        'email',
                    ],
                ],
                [
                    '*.foo.email' => 'bar',
                ],
            ],
            $result
        );
    }

    /**
     * @test
     */
    public function itGeneratesRulesForArrayItself()
    {
        $inputDefinition = PartialParser::inputValueDefinition('
        foo: [String] @rulesForArray(apply: ["email"], messages: { email: "bar" })
        ');

        $result = RuleFactory::getRulesAndMessages($inputDefinition);

        $this->assertSame(
            [
                [
                    'foo' => [
                        'email',
                    ],
                ],
                [
                    'foo.email' => 'bar',
                ],
            ],
            $result
        );
    }

    /**
     * @test
     */
    public function itThrowsIfArrayRulesAreOnNonList()
    {
        $this->expectException(DirectiveException::class);
        $inputDefinition = PartialParser::inputValueDefinition('
        foo: String @rulesForArray
        ');

        RuleFactory::getRulesAndMessages($inputDefinition);
    }

    /**
     * @test
     */
    public function itThrowsIfArrayRulesAreOnNonNullNonList()
    {
        $this->expectException(DirectiveException::class);
        $inputDefinition = PartialParser::inputValueDefinition('
        foo: String! @rulesForArray
        ');

        RuleFactory::getRulesAndMessages($inputDefinition);
    }

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

        $this->assertSame(
            [
                'email' => ['required', 'email'],
            ],
            $rules
        );

        $this->assertSame([], $messages);
    }

    /**
     * @test
     */
    public function itGeneratesArrayValidationRules()
    {
        $documentAST = ASTBuilder::generate('
        type Mutation {
            createUser(emailList: [String] @rules(apply: ["required", "email"])): String
        }
        ');

        list($rules, $messages) = RuleFactory::build(
            'createUser',
            'Mutation',
            [],
            $documentAST
        );

        $this->assertSame(
            [
                '*.emailList' => ['required', 'email'],
            ],
            $rules
        );

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
            address: [AddressInput] @rulesForArray(apply: ["required"])
        }
        
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }
        ');

        $variables = [
            'input' => [
                'address' => [
                    ['street' => 'bar'],
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
            'input.address.0.street' => ['required'],
            'input.address.0.primary' => ['required'],
        ], $rules);

        $this->assertSame([
            'input.address.0.primary.required' => 'foobar',
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
            settings: [Setting] @rulesForArray(apply: ["required"])
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
            'input.settings.0.option' => ['required'],
            'input.settings.0.value' => ['required'],
            'input.settings.0.setting.option' => ['required'],
            'input.settings.0.setting.value' => ['required'],
        ], $rules);

        $this->assertEquals([
            'input.settings.0.value.required' => 'foobar',
            'input.settings.0.setting.value.required' => 'foobar',
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

    public function itGeneratesIndividualRulesForDifferentPaths()
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
            createFoo(input: [FooInput] @rulesForArray(apply: ["required"])): String
        }
        ');

        $variables = [
            'input' => [
                [
                    'required' => 'foobar',
                ],
                [
                    'self' => [
                        'required' => 'barbaz',
                    ],
                ],
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
            'input.0.required' => ['required'],
            'input.1.self.required' => ['required'],
        ], $rules);

        $this->assertEquals([
            'input.0.required.required' => 'foobar',
            'input.1.self.required.required' => 'foobar',
        ], $messages);
    }
}

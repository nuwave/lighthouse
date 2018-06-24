<?php

namespace Tests\Unit\Schema\Factories;

use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Factories\RuleFactory;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Tests\TestCase;

class RuleFactoryTest extends TestCase
{
    use HandlesDirectives;

    /**
     * @test
     */
    public function itCanBuildRulesForInputField()
    {
        $documentAST = DocumentAST::fromSource('
        input UserInput {
            email: String @rules(apply: ["required", "email"])
        }
        
        type Mutation {
            createUser(input: UserInput): String
        }');

        $addressType = $documentAST->inputTypes()->first();
        $input = $addressType->fields[0];

        $documentAST = RuleFactory::build(
            $input->directives[0],
            $input,
            $addressType,
            $documentAST
        );

        $inputArg = $documentAST->mutationType()->fields[0]->arguments[0];

        $this->assertCount(1, $documentAST->mutationType()->fields[0]->arguments);
        $this->assertCount(1, $inputArg->directives);
        $this->assertEquals(
            'input.email',
            $this->directiveArgValue($inputArg->directives[0], 'path')
        );
        $this->assertEquals(
            ['required', 'email'],
            $this->directiveArgValue($inputArg->directives[0], 'apply')
        );
    }

    /**
     * @test
     */
    public function itCanBuildRulesForNestedInputField()
    {
        $documentAST = DocumentAST::fromSource('
        input AddressInput {
            street: String @rules(apply: ["required"])
        }

        input UserInput {
            email: String @rules(apply: ["required", "email"])
            address: AddressInput
        }

        input FooInput {
            bar: String @rules(apply: ["unique"])
        }
        
        type Mutation {
            createUser(input: UserInput): String
        }');

        $addressInputType = $documentAST->inputTypes()[0];
        $streetField = $addressInputType->fields[0];

        $userInputType = $documentAST->inputTypes()[1];
        $emailField = $userInputType->fields[0];

        $fooInputType = $documentAST->inputTypes()[2];
        $barField = $fooInputType->fields[0];

        $documentAST = RuleFactory::build(
            $streetField->directives[0],
            $streetField,
            $addressInputType,
            $documentAST
        );

        $documentAST = RuleFactory::build(
            $emailField->directives[0],
            $emailField,
            $userInputType,
            $documentAST
        );

        $documentAST = RuleFactory::build(
            $barField->directives[0],
            $barField,
            $fooInputType,
            $documentAST
        );

        $inputArg = $documentAST->mutationType()->fields[0]->arguments[0];

        $this->assertCount(1, $documentAST->mutationType()->fields[0]->arguments);
        $this->assertCount(2, $inputArg->directives);

        $this->assertEquals(
            'input.address.street',
            $this->directiveArgValue($inputArg->directives[0], 'path')
        );
        $this->assertEquals(
            ['required'],
            $this->directiveArgValue($inputArg->directives[0], 'apply')
        );

        $this->assertEquals(
            'input.email',
            $this->directiveArgValue($inputArg->directives[1], 'path')
        );
        $this->assertEquals(
            ['required', 'email'],
            $this->directiveArgValue($inputArg->directives[1], 'apply')
        );
    }

    /**
     * @test
     */
    public function itCanApplyRulesToResolver()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        input UserInput {
            email: String @rules(apply: ["required", "email"])
        }
        
        type Mutation {
            createUser(input: UserInput): String
        }');

        dd($schema);
    }
}

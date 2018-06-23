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

        $inputType = $documentAST->inputTypes()->first();
        $input = $inputType->fields[0];

        $documentAST = RuleFactory::build(
            $input->directives[0],
            $input,
            $inputType,
            $documentAST
        );

        $inputArg = $documentAST->mutationType()->fields[0]->arguments[0];

        $this->assertCount(1, $inputArg->directives);

        $this->assertEquals(
            'input',
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
        
        type Mutation {
            createUser(input: UserInput): String
        }');

        $inputType = $documentAST->inputTypes()->first();
        $input = $inputType->fields[0];

        $documentAST = RuleFactory::build(
            $input->directives[0],
            $input,
            $inputType,
            $documentAST
        );

        // TODO: Test Document
    }
}

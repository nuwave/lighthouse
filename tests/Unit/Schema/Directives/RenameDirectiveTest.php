<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class RenameDirectiveTest extends TestCase
{
    public function testCanRenameAField(): void
    {
        $this->schema = "
        type Query {
            bar: Bar @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        
        type Bar {
            bar: String! @rename(attribute: \"baz\")
        }
        ";

        $this->graphQL('
        {
            bar {
                bar
            }
        }
        ')->assertJson([
            'data' => [
                'bar' => [
                    'bar' => 'asdf',
                ],
            ],
        ]);
    }

    public function resolve(): Bar
    {
        return new Bar;
    }

    public function testThrowsAnExceptionIfNoAttributeDefined(): void
    {
        $this->expectException(DirectiveException::class);

        $this->schema = '
        type Query {
            foo: String! @rename
        }
        ';

        $this->graphQL('
        {
            fooBar
        }
        ');
    }
}

class Bar
{
    /**
     * @var string
     */
    public $baz = 'asdf';
}

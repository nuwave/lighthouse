<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Tests\TestCase;
use GraphQL\Error\Error;
use Illuminate\Http\UploadedFile;
use GraphQL\Error\InvariantViolation;
use Nuwave\Lighthouse\Schema\Types\Scalars\Upload;

class UploadTest extends TestCase
{
    public function testThrowsIfSerializing(): void
    {
        $this->expectException(InvariantViolation::class);

        (new Upload)->serialize('');
    }

    public function testThrowsIfParsingLiteral(): void
    {
        $this->expectException(Error::class);

        (new Upload)->parseLiteral('');
    }

    public function testThrowsIfParsingValueNotFile(): void
    {
        $this->expectException(Error::class);

        (new Upload)->parseValue('not a file');
    }

    public function testParsesValidFiles(): void
    {
        $value = UploadedFile::fake()
            ->create('my-file.jpg', 500);
        $parsedValue = (new Upload)->parseValue($value);

        $this->assertEquals($value, $parsedValue);
    }
}

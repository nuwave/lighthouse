<?php

namespace Tests\Unit\Schema\Types\Scalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use Illuminate\Http\UploadedFile;
use Nuwave\Lighthouse\Schema\Types\Scalars\Upload;
use Tests\TestCase;

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

        $upload = new Upload;
        // @phpstan-ignore-next-line Wrong use is on purpose
        $upload->parseLiteral('');
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

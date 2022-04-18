<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Exception;
use Illuminate\Http\UploadedFile;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

class UploadDirective extends BaseDirective implements ArgDirective, ArgTransformerDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Uploads given file to storage, removes the argument and sets 
the returned path to the attribute key provided.

This does not change the schema from a client perspective.
"""
directive @upload(
  """
  The storage disk to be used, defaults to config value `filesystems.default`.
  """
  disk: String
  """
  The path where the file should be stored, defaults to `/`.
  """
  path: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * @throws Exception
     */
    public function transform($argumentValue): ?string
    {
        if ($argumentValue === null) {
            return null;
        }

        if (!($argumentValue instanceof UploadedFile)) {
            throw new Exception("Expected UploadedFile from `{$this->name()}`");
        }

        $filename = $argumentValue->hashName();

        if (!$filepathInStorage = $argumentValue->storeAs($this->pathArgValue(), $filename, $this->diskArgValue())) {
            throw new Exception("Unable to upload `{$this->name()}` file to `{$this->pathArgValue()}` via disk `{$this->diskArgValue()}`");
        }

        return $filepathInStorage;
    }

    /**
     * @throws Exception
     */
    public function diskArgValue(): string
    {
        return $this->directiveArgValue('disk', config('filesystems.default'));
    }

    public function pathArgValue(): string
    {
        return rtrim(
            $this->directiveArgValue('path', ''),
            '\\/'
        );
    }
}

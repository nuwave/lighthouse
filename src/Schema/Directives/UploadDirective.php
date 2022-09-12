<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Exception;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Symfony\Component\HttpFoundation\File\Exception\CannotWriteFileException;

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
  The path where the file should be stored.
  """
  path: String! = "/"
  """
  If the visibility should be public, defaults to false (private).
  """
  public: Boolean! = false
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
            throw new InvalidArgumentException("Expected UploadedFile from `{$this->nodeName()}`");
        }

        $filename = $argumentValue->hashName();

        $filepathInStorage = $argumentValue->storeAs(
            $this->pathArgValue(),
            $filename,
            [
                'disk' => $this->diskArgValue(),
                'visibility' => $this->publicArgValue()
                    ? 'public'
                    : 'private'
            ]
        );

        if ($filepathInStorage === false) {
            throw new CannotWriteFileException("Unable to upload `{$this->nodeName()}` file to `{$this->pathArgValue()}` via disk `{$this->diskArgValue()}`");
        }

        return $filepathInStorage;
    }

    public function diskArgValue(): string
    {
        return $this->directiveArgValue('disk') ?? config('filesystems.default');
    }

    public function pathArgValue(): string
    {
        return $this->directiveArgValue('path') ?? '/';
    }

    public function publicArgValue(): string
    {
        return $this->directiveArgValue('public', false);
    }
}

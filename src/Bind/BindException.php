<?php

declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use Exception;
use GraphQL\Error\ClientAware;
use Illuminate\Support\Str;

use function implode;

class BindException extends Exception implements ClientAware
{
    public static function multipleRecordsFound(mixed $value, BindDefinition $definition): self
    {
        return new self(
            "Unexpectedly found multiple records for binding $definition->nodeName with $definition->column `$value`.",
        );
    }

    public static function notFound(mixed $value, BindDefinition $definition): self
    {
        return new self(
            "No record found for binding $definition->nodeName with $definition->column `$value`.",
        );
    }

    public static function tooManyRecordsFound(mixed $value, BindDefinition $definition): self
    {
        $column = Str::plural($definition->column);

        return new self(
            "Unexpectedly found more records for binding $definition->nodeName with $column `$value`.",
        );
    }

    public static function missingRecords(array $value, BindDefinition $definition): self
    {
        $column = Str::plural($definition->column);
        $ids = implode(',', $value);

        return new self(
            "No records found for binding $definition->nodeName with $column $ids.",
        );
    }

    public function isClientSafe(): bool
    {
        return true;
    }
}

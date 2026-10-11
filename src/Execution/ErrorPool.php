<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\Error;

class ErrorPool
{
    /**
     * The buffered errors.
     *
     * @var array<int, \Throwable>
     */
    protected array $throwables = [];

    /** Stores an error that will be added to the result. */
    public function record(\Throwable $throwable): void
    {
        $this->throwables[] = $throwable;
    }

    /** @return array<\GraphQL\Error\Error> */
    public function errors(): array
    {
        return array_map(
            static function (\Throwable $throwable): Error {
                if ($throwable instanceof Error) {
                    return $throwable;
                }

                return new Error(
                    $throwable->getMessage(),
                    null,
                    null,
                    [],
                    null,
                    $throwable,
                );
            },
            $this->throwables,
        );
    }

    public function clear(): void
    {
        $this->throwables = [];
    }
}

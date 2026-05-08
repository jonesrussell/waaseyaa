<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

/**
 * Read-only access to parsed CLI input (arguments and options).
 */
interface CliInput
{
    /**
     * Retrieve a parsed argument value by name.
     *
     * @return string|int|float|bool|list<mixed>|null
     */
    public function argument(string $name): string|int|float|bool|array|null;

    /**
     * Retrieve a parsed option value by long name.
     *
     * @return string|int|float|bool|list<mixed>|null
     */
    public function option(string $name): string|int|float|bool|array|null;

    /**
     * Return all parsed arguments as an associative array.
     *
     * @return array<string, scalar|array|null>
     */
    public function arguments(): array;

    /**
     * Return all parsed options as an associative array.
     *
     * @return array<string, scalar|array|null>
     */
    public function options(): array;
}
